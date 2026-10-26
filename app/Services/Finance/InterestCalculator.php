<?php

namespace App\Services\Finance;

use App\Enums\RepaymentFrequency;
use App\Support\Money;

/**
 * Conversão de taxas nominais para a taxa de cada período de pagamento.
 *
 * Base temporal: conversão PROPORCIONAL (taxa nominal, sem capitalização composta):
 *   - taxa anual  -> taxa do período = taxa / períodos por ano (12, 26 ou 52)
 *   - taxa mensal -> taxa do período = taxa × 12 / períodos por ano
 * Assim, uma taxa mensal de 5% corresponde a 5% por prestação mensal,
 * 2,3077% por prestação quinzenal e 1,1538% por prestação semanal.
 */
final class InterestCalculator
{
    /** Taxa do período como fracção decimal (ex.: "0.05"). */
    public function periodRate(string $ratePercent, string $ratePeriod, RepaymentFrequency $frequency): string
    {
        $fraction = Money::div($ratePercent, '100');
        $annual = $ratePeriod === 'monthly' ? Money::mul($fraction, '12') : $fraction;

        return Money::div($annual, (string) $frequency->periodsPerYear());
    }

    /** Juros de um período sobre um saldo, arredondados a 2 casas. */
    public function periodInterest(string $balance, string $periodRate): string
    {
        return Money::round(Money::mul($balance, $periodRate));
    }

    /** Taxa anual nominal equivalente (em %), para informação ao cliente. */
    public function annualNominalPercent(string $ratePercent, string $ratePeriod): string
    {
        return Money::round($ratePeriod === 'monthly' ? Money::mul($ratePercent, '12') : $ratePercent, 4);
    }
}
