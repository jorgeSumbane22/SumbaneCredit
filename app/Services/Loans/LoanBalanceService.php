<?php

namespace App\Services\Loans;

use App\Enums\InstallmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Recalcula os saldos em cache do empréstimo a partir das prestações (fonte de verdade
 * operacional) e dos pagamentos confirmados. Nunca altera saldos sem movimento associado.
 */
class LoanBalanceService
{
    public function refresh(Loan $loan, ?CarbonImmutable $asOf = null): Loan
    {
        $asOf ??= CarbonImmutable::today();
        $installments = LoanInstallment::where('loan_id', $loan->id)
            ->where('schedule_version', $loan->schedule_version)
            ->orderBy('number')
            ->get();

        $principalOutstanding = '0';
        $totalOutstanding = '0';
        $next = null;
        $oldestOverdue = null;

        foreach ($installments as $i) {
            if (! in_array($i->status, [InstallmentStatus::Pending, InstallmentStatus::Partial], true)) {
                continue;
            }
            $out = $i->outstandingComponents();
            $principalOutstanding = Money::add($principalOutstanding, $out['principal']);
            $totalOutstanding = Money::add($totalOutstanding, $i->outstandingTotal());
            $next ??= $i->due_date;
            if ($i->due_date->lt($asOf) && $oldestOverdue === null) {
                $oldestOverdue = $i->due_date;
            }
        }

        $loan->principal_outstanding = Money::toDb($principalOutstanding);
        $loan->total_outstanding = Money::toDb($totalOutstanding);
        $loan->total_paid = Money::toDb((string) $loan->payments()->where('status', PaymentStatus::Confirmed)->sum('allocated_amount'));
        $loan->next_due_date = $next;
        $loan->days_past_due = $oldestOverdue ? (int) $oldestOverdue->copy()->startOfDay()->diffInDays($asOf) : 0;
        $loan->save();

        return $loan;
    }

    /** Actualiza os dias de atraso de cada prestação em aberto. */
    public function refreshInstallmentAging(Loan $loan, CarbonImmutable $asOf): void
    {
        LoanInstallment::where('loan_id', $loan->id)
            ->where('schedule_version', $loan->schedule_version)
            ->whereIn('status', InstallmentStatus::openValues())
            ->get()
            ->each(function (LoanInstallment $i) use ($asOf) {
                $days = $i->due_date->lt($asOf) ? (int) $i->due_date->copy()->startOfDay()->diffInDays($asOf) : 0;
                if ($i->days_overdue !== $days) {
                    $i->days_overdue = $days;
                    $i->save();
                }
            });
    }
}
