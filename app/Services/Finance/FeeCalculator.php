<?php

namespace App\Services\Finance;

use App\Support\Money;

/**
 * Encargos contratuais configurados no produto.
 *  - type "fixed": valor fixo em MZN; type "percent": percentagem sobre o capital.
 *  - charge "upfront": deduzido no desembolso (o cliente recebe o líquido);
 *    charge "financed": repartido em partes iguais pelas prestações.
 */
final class FeeCalculator
{
    /**
     * @return array{items: array<int, array{name:string,type:string,value:string,charge:string,amount:string}>, upfront: string, financed: string, total: string}
     */
    public function calculate(string $principal, array $fees): array
    {
        $items = [];
        $upfront = '0';
        $financed = '0';

        foreach ($fees as $fee) {
            $value = Money::of($fee['value'] ?? '0');
            $type = $fee['type'] ?? 'fixed';
            $charge = $fee['charge'] ?? 'upfront';

            $amount = $type === 'percent'
                ? Money::round(Money::div(Money::mul($principal, $value), '100'))
                : Money::round($value);

            if (Money::isZero($amount)) {
                continue;
            }

            $items[] = [
                'name' => (string) ($fee['name'] ?? 'Encargo'),
                'type' => $type,
                'value' => $value,
                'charge' => $charge,
                'amount' => $amount,
            ];

            if ($charge === 'financed') {
                $financed = Money::add($financed, $amount);
            } else {
                $upfront = Money::add($upfront, $amount);
            }
        }

        return [
            'items' => $items,
            'upfront' => Money::toDb($upfront),
            'financed' => Money::toDb($financed),
            'total' => Money::toDb(Money::add($upfront, $financed)),
        ];
    }
}
