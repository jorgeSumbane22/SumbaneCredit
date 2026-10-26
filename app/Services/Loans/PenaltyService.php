<?php

namespace App\Services\Loans;

use App\Enums\InstallmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Penalty;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Finance\PenaltyCalculator;
use App\Services\LedgerService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PenaltyService
{
    public function __construct(
        private readonly PenaltyCalculator $calculator,
        private readonly LedgerService $ledger,
        private readonly LoanBalanceService $balances,
        private readonly AuditService $audit,
    ) {}

    /** Aplica penalizações devidas a um empréstimo (idempotente: uma por prestação). */
    public function applyLateFees(Loan $loan, CarbonImmutable $asOf): int
    {
        if ($loan->penalty_type === 'none') {
            return 0;
        }

        return DB::transaction(function () use ($loan, $asOf) {
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);
            $applied = 0;

            $installments = LoanInstallment::where('loan_id', $loan->id)
                ->where('schedule_version', $loan->schedule_version)
                ->whereIn('status', InstallmentStatus::openValues())
                ->whereDate('due_date', '<', $asOf->subDays($loan->penalty_grace_days)->toDateString())
                ->whereDoesntHave('penalty')
                ->lockForUpdate()
                ->get();

            foreach ($installments as $installment) {
                $amount = $this->calculator->lateFee($installment, $loan->penalty_type, (string) $loan->penalty_value, $loan->penalty_grace_days, $asOf);
                if (! Money::isPositive($amount)) {
                    continue;
                }
                $penalty = Penalty::create([
                    'loan_id' => $loan->id,
                    'loan_installment_id' => $installment->id,
                    'type' => 'late_fee',
                    'amount' => $amount,
                    'applied_on' => $asOf->toDateString(),
                ]);
                $installment->penalty_due = Money::toDb(Money::add($installment->penalty_due, $amount));
                $installment->recalculate();
                $installment->save();
                $this->ledger->post($loan, 'penalty_charge', 'penalty', 'debit', $amount, $penalty, "Penalização por atraso — prestação n.º {$installment->number}");
                $this->audit->log('penalty.applied', $penalty, null, ['amount' => $amount, 'installment' => $installment->number]);
                $applied++;
            }

            if ($applied) {
                $this->balances->refresh($loan);
            }

            return $applied;
        });
    }

    public function waive(Penalty $penalty, User $user, string $reason): Penalty
    {
        return DB::transaction(function () use ($penalty, $user, $reason) {
            $penalty = Penalty::lockForUpdate()->findOrFail($penalty->id);
            if ($penalty->status !== 'applied') {
                throw new BusinessRuleException('Esta penalização já foi dispensada.');
            }
            $loan = Loan::lockForUpdate()->findOrFail($penalty->loan_id);
            $installment = LoanInstallment::lockForUpdate()->findOrFail($penalty->loan_installment_id);
            if ($installment->status === InstallmentStatus::Restructured) {
                throw new BusinessRuleException('A prestação foi reestruturada; a penalização não pode ser dispensada.');
            }

            $outstanding = $installment->outstandingComponents()['penalty'];
            $waive = Money::min($outstanding, (string) $penalty->amount);
            if (! Money::isPositive($waive)) {
                throw new BusinessRuleException('A penalização já foi paga e não pode ser dispensada (seria necessário estornar o pagamento).');
            }

            $installment->penalty_waived = Money::toDb(Money::add($installment->penalty_waived, $waive));
            $installment->recalculate();
            $installment->save();

            $penalty->update(['status' => 'waived', 'waived_by' => $user->id, 'waived_at' => now(), 'waiver_reason' => $reason]);
            $this->ledger->post($loan, 'penalty_waiver', 'penalty', 'credit', $waive, $penalty, 'Dispensa de penalização: '.$reason);
            $this->balances->refresh($loan);
            $this->audit->log('penalty.waived', $penalty, ['status' => 'applied'], ['status' => 'waived', 'amount' => $waive], $reason);

            return $penalty;
        });
    }
}
