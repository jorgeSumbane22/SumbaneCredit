<?php

namespace App\Services\Credit;

use App\Enums\AmortizationMethod;
use App\Enums\RepaymentFrequency;
use App\Exceptions\BusinessRuleException;
use App\Models\LoanProductVersion;
use App\Services\Finance\AmortizationService;
use App\Services\Finance\InterestCalculator;
use App\Services\Finance\LoanTerms;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Simulação de crédito — usada pelo website público, pelo portal do cliente e pelo
 * painel administrativo. Utiliza exactamente o mesmo motor que gera os planos reais.
 */
class LoanSimulationService
{
    public function __construct(
        private readonly AmortizationService $amortization,
        private readonly InterestCalculator $interest,
    ) {}

    public function terms(LoanProductVersion $version, string $amount, int $term, ?string $frequency = null, ?string $method = null): LoanTerms
    {
        $amount = Money::toDb($amount);
        $frequencyEnum = $frequency ? RepaymentFrequency::tryFrom($frequency) : $version->frequencies()[0];
        $methodEnum = $method ? AmortizationMethod::tryFrom($method) : $version->methods()[0];

        if (! $frequencyEnum || ! in_array($frequencyEnum->value, $version->allowed_frequencies, true)) {
            throw new BusinessRuleException('A periodicidade escolhida não está disponível para este produto.');
        }
        if (! $methodEnum || ! in_array($methodEnum->value, $version->amortization_methods, true)) {
            throw new BusinessRuleException('O método de amortização escolhido não está disponível para este produto.');
        }
        if (Money::lt($amount, $version->min_amount) || Money::gt($amount, $version->max_amount)) {
            throw new BusinessRuleException(sprintf('O montante deve estar entre %s e %s.', Money::format($version->min_amount), Money::format($version->max_amount)));
        }
        if ($term < $version->min_term || $term > $version->max_term) {
            throw new BusinessRuleException(sprintf('O número de prestações deve estar entre %d e %d.', $version->min_term, $version->max_term));
        }

        return new LoanTerms($amount, (string) $version->interest_rate, $version->rate_period, $frequencyEnum, $methodEnum, $term, $version->fees ?? []);
    }

    public function simulate(LoanProductVersion $version, string $amount, int $term, ?string $frequency = null, ?string $method = null, ?DateTimeInterface $start = null): array
    {
        $terms = $this->terms($version, $amount, $term, $frequency, $method);
        $schedule = $this->amortization->schedule($terms, $start ?? CarbonImmutable::today());

        return $schedule + [
            'product' => [
                'id' => $version->loan_product_id,
                'name' => $version->product?->name,
                'version' => $version->version,
            ],
            'conditions' => [
                'amount' => $terms->principal,
                'term' => $terms->term,
                'frequency' => $terms->frequency->value,
                'frequency_label' => $terms->frequency->label(),
                'method' => $terms->method->value,
                'method_label' => $terms->method->label(),
                'rate_label' => $version->rateLabel(),
                'annual_nominal_rate' => $this->interest->annualNominalPercent((string) $version->interest_rate, $version->rate_period),
                'penalty_label' => $version->penaltyLabel(),
                'early_settlement_label' => $version->earlySettlementLabel(),
            ],
            'disclaimer' => 'Simulação indicativa. Não constitui aprovação de crédito nem proposta contratual. As condições finais dependem da análise e da aprovação do pedido.',
        ];
    }

    /** Formato JSON para o simulador (Fetch API). */
    public function toJson(array $s): array
    {
        $fmt = fn ($v) => Money::format($v);

        return [
            'installment' => $fmt($s['first_installment']),
            'max_installment' => $fmt($s['max_installment']),
            'total_interest' => $fmt($s['totals']['interest']),
            'fees_upfront' => $fmt($s['totals']['fees_upfront']),
            'fees_financed' => $fmt($s['totals']['fees_financed']),
            'fees_total' => $fmt($s['totals']['fees_total']),
            'total_payable' => $fmt($s['totals']['installments']),
            'net_disbursed' => $fmt($s['totals']['net_disbursed']),
            'cost_of_credit' => $fmt($s['totals']['cost_of_credit']),
            'first_due_date' => CarbonImmutable::parse($s['first_due_date'])->format('d/m/Y'),
            'maturity_date' => CarbonImmutable::parse($s['maturity_date'])->format('d/m/Y'),
            'conditions' => $s['conditions'],
            'fees' => array_map(fn ($f) => ['name' => $f['name'], 'amount' => $fmt($f['amount']), 'charge' => $f['charge'] === 'financed' ? 'Repartido pelas prestações' : 'Deduzido no desembolso'], $s['fees']),
            'rows' => array_map(fn ($r) => [
                'number' => $r['number'],
                'due_date' => CarbonImmutable::parse($r['due_date'])->format('d/m/Y'),
                'opening_balance' => $fmt($r['opening_balance']),
                'principal' => $fmt($r['principal']),
                'interest' => $fmt($r['interest']),
                'fee' => $fmt($r['fee']),
                'total' => $fmt($r['total']),
                'closing_balance' => $fmt($r['closing_balance']),
            ], $s['rows']),
            'disclaimer' => $s['disclaimer'],
        ];
    }
}
