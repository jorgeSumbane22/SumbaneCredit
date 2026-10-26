<?php

namespace App\Services\Finance;

use App\Enums\RepaymentFrequency;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Datas de vencimento: a n-ésima prestação vence n períodos após a data de início
 * (data efectiva do desembolso). Mensal: mesmo dia do mês, sem ultrapassar o fim
 * do mês (ex.: 31/01 -> 28/02). Quinzenal: +14 dias. Semanal: +7 dias.
 */
final class DueDateCalculator
{
    public function dueDate(DateTimeInterface $start, RepaymentFrequency $frequency, int $number): CarbonImmutable
    {
        $start = CarbonImmutable::instance($start)->startOfDay();

        return match ($frequency) {
            RepaymentFrequency::Monthly => $start->addMonthsNoOverflow($number),
            RepaymentFrequency::Biweekly => $start->addDays(14 * $number),
            RepaymentFrequency::Weekly => $start->addDays(7 * $number),
        };
    }
}
