<?php

namespace App\Services\Finance;

use App\Models\LoanInstallment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Penalização por atraso (configurada no produto e copiada para o contrato).
 *  - Aplica-se uma única vez por prestação, quando o atraso excede o período de carência.
 *  - "fixed": valor fixo em MZN; "percent": % sobre o valor em atraso da prestação
 *    (capital + juros + encargos, excluindo penalizações).
 *  - "none": sem penalizações.
 * O sistema não impõe limites legais: a instituição deve validar os valores configurados.
 */
final class PenaltyCalculator
{
    public function daysOverdue(LoanInstallment $installment, DateTimeInterface $asOf): int
    {
        $due = CarbonImmutable::parse($installment->due_date)->startOfDay();
        $asOf = CarbonImmutable::instance($asOf)->startOfDay();

        return $asOf->gt($due) ? (int) $due->diffInDays($asOf) : 0;
    }

    public function lateFee(LoanInstallment $installment, string $type, string $value, int $graceDays, DateTimeInterface $asOf): string
    {
        if ($type === 'none' || ! Money::isPositive($value)) {
            return '0.00';
        }
        if ($this->daysOverdue($installment, $asOf) <= $graceDays) {
            return '0.00';
        }

        $out = $installment->outstandingComponents();
        $base = Money::add($out['principal'], $out['interest'], $out['fee']);
        if (! Money::isPositive($base)) {
            return '0.00';
        }

        return $type === 'percent'
            ? Money::round(Money::div(Money::mul($base, $value), '100'))
            : Money::round($value);
    }
}
