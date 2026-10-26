<?php

namespace App\Services\Loans;

use App\Enums\AmortizationMethod;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentStatus;
use App\Enums\RepaymentFrequency;
use App\Exceptions\BusinessRuleException;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanRestructuring;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Finance\LoanTerms;
use App\Services\LedgerService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reestruturação autorizada: encerra as prestações em aberto do plano actual e gera um
 * novo plano (nova versão) sobre o capital em dívida, opcionalmente capitalizando juros,
 * encargos e penalizações vencidos. O contrato original e o histórico são preservados.
 */
class RestructuringService
{
    public function __construct(
        private readonly ScheduleService $schedule,
        private readonly LedgerService $ledger,
        private readonly LoanBalanceService $balances,
        private readonly AuditService $audit,
    ) {}

    public function restructure(Loan $loan, User $user, array $data): LoanRestructuring
    {
        return DB::transaction(function () use ($loan, $user, $data) {
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);
            if ($loan->status !== LoanStatus::Active) {
                throw new BusinessRuleException('Só empréstimos activos podem ser reestruturados.');
            }
            if (Payment::where('loan_id', $loan->id)->where('status', PaymentStatus::Pending)->exists()) {
                throw new BusinessRuleException('Existem pagamentos pendentes de confirmação. Processe-os antes de reestruturar.');
            }
            if ($loan->schedule_version >= 250) {
                throw new BusinessRuleException('Número máximo de reestruturações atingido.');
            }

            $today = CarbonImmutable::today();
            $open = LoanInstallment::where('loan_id', $loan->id)->where('schedule_version', $loan->schedule_version)
                ->whereIn('status', InstallmentStatus::openValues())->orderBy('number')->lockForUpdate()->get();
            if ($open->isEmpty()) {
                throw new BusinessRuleException('Não existem prestações em aberto.');
            }

            $principal = '0';
            $dueNonPrincipal = '0';
            $closing = ['principal' => '0', 'interest' => '0', 'fee' => '0', 'penalty' => '0'];
            foreach ($open as $i) {
                $out = $i->outstandingComponents();
                $principal = Money::add($principal, $out['principal']);
                if ($i->due_date->lte($today)) {
                    $dueNonPrincipal = Money::add($dueNonPrincipal, $out['interest'], $out['fee'], $out['penalty']);
                }
                foreach ($closing as $c => $v) {
                    $closing[$c] = Money::add($v, $out[$c]);
                }
            }

            $capitalize = ! empty($data['capitalize_overdue']);
            if (! $capitalize && Money::isPositive($dueNonPrincipal)) {
                throw new BusinessRuleException('Existem juros/encargos/penalizações vencidos ('.Money::format($dueNonPrincipal).'). Capitalize-os ou regularize-os antes de reestruturar.');
            }
            $capitalized = $capitalize ? Money::toDb($dueNonPrincipal) : '0.00';
            $newPrincipal = Money::toDb(Money::add($principal, $capitalized));

            $terms = new LoanTerms(
                $newPrincipal,
                Money::of($data['interest_rate'] ?? (string) $loan->interest_rate),
                $loan->rate_period,
                RepaymentFrequency::from($data['repayment_frequency'] ?? $loan->repayment_frequency->value),
                AmortizationMethod::from($data['amortization_method'] ?? $loan->amortization_method->value),
                (int) $data['term'],
                [],
            );

            // Encerra o plano actual
            foreach ($open as $i) {
                $i->status = InstallmentStatus::Restructured;
                $i->save();
            }
            $previous = $loan->schedule_version;
            $this->ledger->postComponents($loan, 'restructure_close', 'credit', $closing, $loan, "Encerramento do plano v{$previous} (reestruturação)");

            // Novo plano
            $newVersion = $previous + 1;
            $start = CarbonImmutable::parse($data['start_date'] ?? $today);
            $schedule = $this->schedule->generate($loan, $terms, $start, $newVersion);
            $loan->schedule_version = $newVersion;
            $loan->maturity_date = $schedule['maturity_date'];
            $loan->save();

            $this->ledger->post($loan, 'restructure_open', 'principal', 'debit', $newPrincipal, $loan, "Capital do plano v{$newVersion}");
            $this->ledger->post($loan, 'restructure_open', 'interest', 'debit', $schedule['totals']['interest'], $loan, "Juros do plano v{$newVersion}");

            $record = LoanRestructuring::create([
                'loan_id' => $loan->id,
                'previous_schedule_version' => $previous,
                'new_schedule_version' => $newVersion,
                'outstanding_principal' => Money::toDb($principal),
                'capitalized_amount' => $capitalized,
                'new_principal' => $newPrincipal,
                'new_term' => $terms->term,
                'new_interest_rate' => $terms->interestRate,
                'new_amortization_method' => $terms->method->value,
                'new_repayment_frequency' => $terms->frequency->value,
                'first_due_date' => $schedule['first_due_date'],
                'reason' => $data['reason'],
                'approved_by' => $user->id,
            ]);

            $this->balances->refresh($loan);
            $loan->statusHistory()->create([
                'from_status' => $loan->status->value, 'to_status' => $loan->status->value, 'user_id' => $user->id,
                'reason' => "Reestruturação: novo plano v{$newVersion} com {$terms->term} prestações. {$data['reason']}",
            ]);
            $this->audit->log('loan.restructured', $loan, ['schedule_version' => $previous], [
                'schedule_version' => $newVersion, 'new_principal' => $newPrincipal, 'term' => $terms->term,
            ], $data['reason']);

            return $record;
        });
    }
}
