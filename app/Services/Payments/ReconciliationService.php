<?php

namespace App\Services\Payments;

use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ReconciliationRecord;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LedgerService;
use App\Support\Money;

/**
 * Reconciliação:
 *  - por empréstimo: saldo em cache × prestações × razão × pagamentos/imputações;
 *  - por método e período: total de pagamentos confirmados × total declarado no extracto.
 */
class ReconciliationService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditService $audit,
    ) {}

    public function checkLoan(Loan $loan): array
    {
        $installments = LoanInstallment::where('loan_id', $loan->id)->where('schedule_version', $loan->schedule_version)->get();
        $byComponent = ['principal' => '0', 'interest' => '0', 'fee' => '0', 'penalty' => '0'];
        foreach ($installments as $i) {
            if ($i->status === InstallmentStatus::Restructured) {
                continue;
            }
            foreach ($i->outstandingComponents() as $c => $v) {
                $byComponent[$c] = Money::add($byComponent[$c], $v);
            }
        }
        $installmentsTotal = Money::toDb(Money::add(...array_values($byComponent)));
        $ledger = $this->ledger->balances($loan);

        $confirmed = Money::toDb((string) Payment::where('loan_id', $loan->id)->where('status', PaymentStatus::Confirmed)->sum('amount'));
        $allocated = Money::toDb((string) PaymentAllocation::whereHas('payment', fn ($q) => $q->where('loan_id', $loan->id)->where('status', PaymentStatus::Confirmed))
            ->where('is_reversed', false)->sum('total'));

        $checks = [
            ['label' => 'Saldo do empréstimo × prestações', 'expected' => $installmentsTotal, 'actual' => Money::toDb((string) $loan->total_outstanding)],
            ['label' => 'Prestações × razão (total)', 'expected' => $installmentsTotal, 'actual' => $ledger['total']],
        ];
        foreach ($byComponent as $c => $v) {
            $checks[] = ['label' => 'Prestações × razão ('.$c.')', 'expected' => Money::toDb($v), 'actual' => $ledger[$c]];
        }
        $checks[] = ['label' => 'Pagamentos confirmados × imputações', 'expected' => $confirmed, 'actual' => $allocated];

        foreach ($checks as &$check) {
            $check['difference'] = Money::toDb(Money::sub($check['actual'], $check['expected']));
            $check['ok'] = Money::isZero($check['difference']);
        }

        return [
            'checks' => $checks,
            'matched' => collect($checks)->every('ok'),
            'installments_total' => $installmentsTotal,
            'ledger' => $ledger,
        ];
    }

    public function reconcileLoan(Loan $loan, ?User $user, ?string $notes = null): ReconciliationRecord
    {
        $result = $this->checkLoan($loan);
        $differences = collect($result['checks'])->reduce(fn ($c, $x) => Money::add($c, ltrim($x['difference'], '-')), '0');

        $record = ReconciliationRecord::create([
            'type' => 'loan',
            'loan_id' => $loan->id,
            'expected_amount' => $result['installments_total'],
            'actual_amount' => $result['ledger']['total'],
            'difference' => Money::toDb($differences),
            'status' => $result['matched'] ? 'matched' : 'mismatch',
            'details' => $result['checks'],
            'notes' => $notes,
            'performed_by' => $user?->id,
        ]);
        $this->audit->log('reconciliation.loan', $record, null, ['loan' => $loan->loan_number, 'status' => $record->status], null, $result['matched'] ? 'success' : 'failure');

        return $record;
    }

    public function reconcileMethodPeriod(string $method, string $start, string $end, string $declared, User $user, ?string $notes): ReconciliationRecord
    {
        $payments = Payment::where('method', PaymentMethod::from($method))
            ->where('status', PaymentStatus::Confirmed)
            ->whereBetween('paid_at', [$start, $end])
            ->get(['id', 'payment_number', 'amount', 'reference', 'paid_at']);

        $expected = Money::toDb(Money::sum($payments, 'amount'));
        $declared = Money::toDb($declared);
        $difference = Money::toDb(Money::sub($declared, $expected));

        $record = ReconciliationRecord::create([
            'type' => 'method_period',
            'method' => $method,
            'period_start' => $start,
            'period_end' => $end,
            'expected_amount' => $expected,
            'actual_amount' => $declared,
            'difference' => $difference,
            'status' => Money::isZero($difference) ? 'matched' : 'mismatch',
            'details' => ['payments_count' => $payments->count(), 'payments' => $payments->map(fn ($p) => [
                'number' => $p->payment_number, 'amount' => (string) $p->amount, 'reference' => $p->reference, 'paid_at' => $p->paid_at->toDateString(),
            ])->all()],
            'notes' => $notes,
            'performed_by' => $user->id,
        ]);
        $this->audit->log('reconciliation.method', $record, null, ['method' => $method, 'status' => $record->status, 'difference' => $difference]);

        return $record;
    }
}
