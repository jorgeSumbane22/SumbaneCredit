<?php

namespace App\Services\Finance;

use App\Models\LoanInstallment;
use App\Support\Money;

/**
 * Distribuição (imputação) de um pagamento pelas prestações em aberto.
 *
 * Regra: prestações por ordem de vencimento (mais antiga primeiro). Dentro de cada
 * prestação, os componentes são liquidados pela ordem configurada
 * (por omissão: penalizações → encargos → juros → capital). Só se passa à prestação
 * seguinte quando a anterior fica totalmente paga. Valores que excedam as prestações
 * vencidas são aplicados às prestações seguintes (pagamento antecipado de prestações).
 *
 * Esta classe é pura: calcula as imputações sem alterar os modelos.
 */
final class PaymentAllocator
{
    public const DEFAULT_ORDER = ['penalty', 'fee', 'interest', 'principal'];

    /**
     * @param  iterable<LoanInstallment>  $installments  prestações em aberto, ordenadas por vencimento
     * @return array{allocations: array<int, array{installment: LoanInstallment, principal:string, interest:string, fee:string, penalty:string, total:string}>, remaining: string}
     */
    public function allocate(iterable $installments, string $amount, array $order = self::DEFAULT_ORDER): array
    {
        $order = $this->normalizeOrder($order);
        $remaining = Money::toDb($amount);
        $allocations = [];

        foreach ($installments as $installment) {
            if (! Money::isPositive($remaining)) {
                break;
            }

            $outstanding = $installment->outstandingComponents();
            $parts = ['principal' => '0.00', 'interest' => '0.00', 'fee' => '0.00', 'penalty' => '0.00'];

            foreach ($order as $component) {
                if (! Money::isPositive($remaining)) {
                    break;
                }
                $take = Money::min($remaining, $outstanding[$component]);
                if (Money::isPositive($take)) {
                    $parts[$component] = Money::toDb($take);
                    $remaining = Money::toDb(Money::sub($remaining, $take));
                }
            }

            $total = Money::toDb(Money::add(...array_values($parts)));
            if (Money::isPositive($total)) {
                $allocations[] = ['installment' => $installment] + $parts + ['total' => $total];
            }
        }

        return ['allocations' => $allocations, 'remaining' => $remaining];
    }

    private function normalizeOrder(array $order): array
    {
        $valid = array_values(array_intersect($order, self::DEFAULT_ORDER));

        return count($valid) === 4 ? $valid : self::DEFAULT_ORDER;
    }
}
