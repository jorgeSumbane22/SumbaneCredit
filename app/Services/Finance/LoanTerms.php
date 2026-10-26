<?php

namespace App\Services\Finance;

use App\Enums\AmortizationMethod;
use App\Enums\RepaymentFrequency;
use App\Support\Money;
use InvalidArgumentException;

/**
 * Condições financeiras necessárias para gerar um plano de amortização.
 *
 * - $interestRate: taxa nominal em percentagem (ex.: "5" = 5%).
 * - $ratePeriod: período a que a taxa se refere ("monthly" ou "annual").
 * - $fees: [['name'=>..., 'type'=>'fixed'|'percent', 'value'=>..., 'charge'=>'upfront'|'financed'], ...]
 */
final class LoanTerms
{
    public function __construct(
        public readonly string $principal,
        public readonly string $interestRate,
        public readonly string $ratePeriod,
        public readonly RepaymentFrequency $frequency,
        public readonly AmortizationMethod $method,
        public readonly int $term,
        public readonly array $fees = [],
    ) {
        if (! Money::isPositive($principal)) {
            throw new InvalidArgumentException('O montante tem de ser superior a zero.');
        }
        if ($term < 1 || $term > 600) {
            throw new InvalidArgumentException('O número de prestações é inválido.');
        }
        if (Money::lt($interestRate, '0')) {
            throw new InvalidArgumentException('A taxa de juro não pode ser negativa.');
        }
        if (! in_array($ratePeriod, ['monthly', 'annual'], true)) {
            throw new InvalidArgumentException('Periodicidade da taxa inválida.');
        }
    }
}
