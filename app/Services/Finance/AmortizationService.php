<?php

namespace App\Services\Finance;

use App\Enums\AmortizationMethod;
use App\Support\Money;
use DateTimeInterface;

/**
 * Gera planos de amortização.
 *
 * Regras de arredondamento (explícitas):
 *  - Cada componente (capital, juros, encargos) é arredondado a 2 casas, meio para cima.
 *  - A última prestação absorve as diferenças de arredondamento: o seu capital é igual
 *    ao saldo remanescente, garantindo que a soma do capital = montante financiado.
 *  - Nos juros simples o total de juros é calculado primeiro (P × i × n) e repartido;
 *    a última prestação absorve o resto.
 *  - Encargos financiados são repartidos em partes iguais; a última absorve o resto.
 */
final class AmortizationService
{
    public function __construct(
        private readonly InterestCalculator $interest = new InterestCalculator,
        private readonly FeeCalculator $fees = new FeeCalculator,
        private readonly DueDateCalculator $dates = new DueDateCalculator,
    ) {}

    /**
     * @return array{
     *   period_rate: string,
     *   rows: array<int, array{number:int,due_date:string,opening_balance:string,principal:string,interest:string,fee:string,total:string,closing_balance:string}>,
     *   totals: array{principal:string,interest:string,fees_financed:string,fees_upfront:string,fees_total:string,installments:string,net_disbursed:string,cost_of_credit:string},
     *   fees: array,
     *   first_installment: string,
     *   max_installment: string,
     *   first_due_date: string,
     *   maturity_date: string
     * }
     */
    public function schedule(LoanTerms $terms, DateTimeInterface $startDate): array
    {
        $n = $terms->term;
        $principal = Money::toDb($terms->principal);
        $rate = $this->interest->periodRate($terms->interestRate, $terms->ratePeriod, $terms->frequency);
        $fees = $this->fees->calculate($principal, $terms->fees);

        $principalParts = $this->principalParts($terms->method, $principal, $rate, $n);
        $feeParts = $this->split($fees['financed'], $n);
        $flatInterest = $terms->method === AmortizationMethod::Flat
            ? $this->split(Money::round(Money::mul(Money::mul($principal, $rate), (string) $n)), $n)
            : null;

        $rows = [];
        $balance = $principal;
        $totalInterest = '0';
        $totalInstallments = '0';
        $maxInstallment = '0';

        for ($i = 1; $i <= $n; $i++) {
            $interest = $flatInterest !== null
                ? $flatInterest[$i - 1]
                : $this->interest->periodInterest($balance, $rate);

            if ($terms->method === AmortizationMethod::Annuity) {
                $payment = $principalParts['payment'];
                $principalPart = $i === $n ? $balance : Money::round(Money::sub($payment, $interest));
                // Protecção: o capital amortizado nunca excede o saldo.
                $principalPart = Money::min($principalPart, $balance);
            } else {
                $principalPart = $i === $n ? $balance : $principalParts['parts'][$i - 1];
            }

            $fee = $feeParts[$i - 1];
            $total = Money::toDb(Money::add($principalPart, $interest, $fee));
            $closing = Money::toDb(Money::sub($balance, $principalPart));

            $rows[] = [
                'number' => $i,
                'due_date' => $this->dates->dueDate($startDate, $terms->frequency, $i)->toDateString(),
                'opening_balance' => Money::toDb($balance),
                'principal' => Money::toDb($principalPart),
                'interest' => Money::toDb($interest),
                'fee' => Money::toDb($fee),
                'total' => $total,
                'closing_balance' => $closing,
            ];

            $balance = $closing;
            $totalInterest = Money::add($totalInterest, $interest);
            $totalInstallments = Money::add($totalInstallments, $total);
            $maxInstallment = Money::max($maxInstallment, $total);
        }

        return [
            'period_rate' => $rate,
            'rows' => $rows,
            'totals' => [
                'principal' => $principal,
                'interest' => Money::toDb($totalInterest),
                'fees_financed' => $fees['financed'],
                'fees_upfront' => $fees['upfront'],
                'fees_total' => $fees['total'],
                'installments' => Money::toDb($totalInstallments),
                'net_disbursed' => Money::toDb(Money::sub($principal, $fees['upfront'])),
                'cost_of_credit' => Money::toDb(Money::add($totalInterest, $fees['total'])),
            ],
            'fees' => $fees['items'],
            'first_installment' => $rows[0]['total'],
            'max_installment' => Money::toDb($maxInstallment),
            'first_due_date' => $rows[0]['due_date'],
            'maturity_date' => $rows[$n - 1]['due_date'],
        ];
    }

    /** Prestação constante (sistema francês): P·i / (1 − (1+i)^−n). */
    public function annuityPayment(string $principal, string $rate, int $n): string
    {
        if (Money::isZero($rate)) {
            return Money::round(Money::div($principal, (string) $n));
        }
        $factor = Money::pow(Money::add('1', $rate), $n);           // (1+i)^n
        $numerator = Money::mul(Money::mul($principal, $rate), $factor);
        $denominator = Money::sub($factor, '1');

        return Money::round(Money::div($numerator, $denominator));
    }

    private function principalParts(AmortizationMethod $method, string $principal, string $rate, int $n): array
    {
        if ($method === AmortizationMethod::Annuity) {
            return ['payment' => $this->annuityPayment($principal, $rate, $n)];
        }

        return ['parts' => $this->split($principal, $n)];
    }

    /** Reparte um valor em n partes iguais; a última absorve o resto do arredondamento. */
    public function split(string $amount, int $n): array
    {
        $part = Money::round(Money::div($amount, (string) $n));
        $parts = array_fill(0, $n, $part);
        $parts[$n - 1] = Money::toDb(Money::sub($amount, Money::mul($part, (string) ($n - 1))));

        return $parts;
    }
}
