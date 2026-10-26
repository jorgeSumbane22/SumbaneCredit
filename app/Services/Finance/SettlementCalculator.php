<?php

namespace App\Services\Finance;

use App\Models\LoanInstallment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Cálculo do montante de liquidação antecipada.
 *
 * Regras:
 *  - Prestações vencidas (vencimento <= data): devem-se integralmente (capital, juros,
 *    encargos e penalizações em aberto).
 *  - Prestação do período em curso (primeira com vencimento > data): deve-se o capital,
 *    os encargos e os juros do período (o período já começou a decorrer).
 *  - Prestações futuras seguintes: deve-se o capital e os encargos financiados.
 *    Os juros futuros são:
 *      * "waive_future_interest": dispensados (registados como juros perdoados);
 *      * "full_contractual": devidos integralmente.
 */
final class SettlementCalculator
{
    /**
     * @param  iterable<LoanInstallment>  $installments  prestações em aberto, ordenadas por vencimento
     * @return array{principal:string, interest:string, fee:string, penalty:string, total:string, interest_waived:string, waivers: array<int,string>}
     */
    public function quote(iterable $installments, DateTimeInterface $asOf, string $rule): array
    {
        $asOf = CarbonImmutable::instance($asOf)->startOfDay();
        $totals = ['principal' => '0', 'interest' => '0', 'fee' => '0', 'penalty' => '0'];
        $waived = '0';
        $waivers = [];
        $currentPeriodSeen = false;

        foreach ($installments as $installment) {
            $out = $installment->outstandingComponents();
            $isFuture = CarbonImmutable::parse($installment->due_date)->startOfDay()->gt($asOf);

            $interest = $out['interest'];
            if ($isFuture) {
                if (! $currentPeriodSeen) {
                    $currentPeriodSeen = true; // período em curso: juros devidos
                } elseif ($rule === 'waive_future_interest' && Money::isPositive($interest)) {
                    $waived = Money::add($waived, $interest);
                    $waivers[$installment->id ?? $installment->number] = Money::toDb($interest);
                    $interest = '0';
                }
            }

            $totals['principal'] = Money::add($totals['principal'], $out['principal']);
            $totals['interest'] = Money::add($totals['interest'], $interest);
            $totals['fee'] = Money::add($totals['fee'], $out['fee']);
            $totals['penalty'] = Money::add($totals['penalty'], $out['penalty']);
        }

        $result = array_map(fn ($v) => Money::toDb($v), $totals);
        $result['total'] = Money::toDb(Money::add(...array_values($totals)));
        $result['interest_waived'] = Money::toDb($waived);
        $result['waivers'] = $waivers;

        return $result;
    }
}
