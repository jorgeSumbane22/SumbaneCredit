<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Services\Finance\AmortizationService;
use App\Services\Finance\LoanTerms;
use DateTimeInterface;

/**
 * Gera as prestações persistidas de um empréstimo a partir das condições contratuais.
 */
class ScheduleService
{
    public function __construct(private readonly AmortizationService $amortization) {}

    public function contractTerms(Loan $loan): LoanTerms
    {
        return new LoanTerms(
            (string) $loan->principal_amount,
            (string) $loan->interest_rate,
            $loan->rate_period,
            $loan->repayment_frequency,
            $loan->amortization_method,
            $loan->term,
            $loan->fees ?? [],
        );
    }

    public function preview(Loan $loan, DateTimeInterface $start): array
    {
        return $this->amortization->schedule($this->contractTerms($loan), $start);
    }

    /** Cria as prestações para a versão de plano indicada. */
    public function generate(Loan $loan, LoanTerms $terms, DateTimeInterface $start, int $scheduleVersion): array
    {
        $schedule = $this->amortization->schedule($terms, $start);

        foreach ($schedule['rows'] as $row) {
            $installment = $loan->allInstallments()->make([
                'schedule_version' => $scheduleVersion,
                'number' => $row['number'],
                'due_date' => $row['due_date'],
                'opening_balance' => $row['opening_balance'],
                'principal_due' => $row['principal'],
                'interest_due' => $row['interest'],
                'fee_due' => $row['fee'],
            ]);
            $installment->recalculate();
            $installment->save();
        }

        return $schedule;
    }
}
