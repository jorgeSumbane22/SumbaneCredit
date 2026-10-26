<?php

namespace Tests\Unit;

use App\Enums\AmortizationMethod;
use App\Enums\RepaymentFrequency;
use App\Services\Finance\AmortizationService;
use App\Services\Finance\InterestCalculator;
use App\Services\Finance\LoanTerms;
use App\Support\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AmortizationServiceTest extends TestCase
{
    private AmortizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AmortizationService;
    }

    private function terms(string $principal, string $rate, AmortizationMethod $method, int $n, array $fees = [], string $ratePeriod = 'monthly', RepaymentFrequency $freq = RepaymentFrequency::Monthly): LoanTerms
    {
        return new LoanTerms($principal, $rate, $ratePeriod, $freq, $method, $n, $fees);
    }

    public function test_annuity_matches_reference_values(): void
    {
        // 10 000 MZN, 5% ao mês, 12 prestações. Prestação de referência: 1 128,25 MZN.
        $s = $this->service->schedule($this->terms('10000', '5', AmortizationMethod::Annuity, 12), CarbonImmutable::parse('2026-01-15'));

        $this->assertSame('1128.25', $s['first_installment']);
        $this->assertCount(12, $s['rows']);
        $this->assertSame('500.00', $s['rows'][0]['interest']);   // 10 000 × 5%
        $this->assertSame('628.25', $s['rows'][0]['principal']);
        $this->assertSame('10000.00', Money::toDb(Money::sum($s['rows'], 'principal')));
        $this->assertSame('0.00', $s['rows'][11]['closing_balance']);
        $this->assertSame($s['totals']['installments'], Money::toDb(Money::add($s['totals']['principal'], $s['totals']['interest'])));
        // A última prestação absorve o arredondamento acumulado (prestação exacta = 1128,2541...).
        $this->assertSame('1074.60', $s['rows'][11]['principal']);
        $this->assertSame('1128.33', $s['rows'][11]['total']);
        $this->assertTrue(Money::lte(Money::sub($s['rows'][11]['total'], '1128.25'), '0.10'));
    }

    public function test_equal_principal_has_constant_amortization_and_declining_interest(): void
    {
        $s = $this->service->schedule($this->terms('12000', '2', AmortizationMethod::EqualPrincipal, 12), CarbonImmutable::parse('2026-01-01'));

        foreach ($s['rows'] as $row) {
            $this->assertSame('1000.00', $row['principal']);
        }
        $this->assertSame('240.00', $s['rows'][0]['interest']);  // 12 000 × 2%
        $this->assertSame('20.00', $s['rows'][11]['interest']);  // 1 000 × 2%
        $this->assertSame('1560.00', $s['totals']['interest']);  // 2% × 1000 × (12+11+...+1)
    }

    public function test_flat_interest_is_calculated_on_initial_principal(): void
    {
        $s = $this->service->schedule($this->terms('10000', '3', AmortizationMethod::Flat, 6), CarbonImmutable::parse('2026-01-01'));

        $this->assertSame('1800.00', $s['totals']['interest']); // 10 000 × 3% × 6
        foreach ($s['rows'] as $row) {
            $this->assertSame('300.00', $row['interest']);
        }
        $this->assertSame('11800.00', $s['totals']['installments']);
    }

    public function test_rounding_remainders_go_to_last_installment(): void
    {
        $s = $this->service->schedule($this->terms('1000', '0', AmortizationMethod::EqualPrincipal, 3), CarbonImmutable::parse('2026-01-01'));

        $this->assertSame(['333.33', '333.33', '333.34'], array_column($s['rows'], 'principal'));
        $this->assertSame('1000.00', Money::toDb(Money::sum($s['rows'], 'principal')));
    }

    public function test_zero_interest_annuity(): void
    {
        $s = $this->service->schedule($this->terms('900', '0', AmortizationMethod::Annuity, 3), CarbonImmutable::parse('2026-01-01'));
        $this->assertSame('300.00', $s['first_installment']);
        $this->assertSame('0.00', $s['totals']['interest']);
    }

    public function test_upfront_and_financed_fees(): void
    {
        $fees = [
            ['name' => 'Comissão de abertura', 'type' => 'percent', 'value' => '2', 'charge' => 'upfront'],
            ['name' => 'Seguro', 'type' => 'fixed', 'value' => '100', 'charge' => 'financed'],
        ];
        $s = $this->service->schedule($this->terms('10000', '0', AmortizationMethod::EqualPrincipal, 3, $fees), CarbonImmutable::parse('2026-01-01'));

        $this->assertSame('200.00', $s['totals']['fees_upfront']);
        $this->assertSame('100.00', $s['totals']['fees_financed']);
        $this->assertSame('9800.00', $s['totals']['net_disbursed']);
        $this->assertSame(['33.33', '33.33', '33.34'], array_column($s['rows'], 'fee'));
        $this->assertSame('10100.00', $s['totals']['installments']);
        $this->assertSame('300.00', $s['totals']['cost_of_credit']);
    }

    public function test_monthly_due_dates_do_not_overflow_month_end(): void
    {
        $s = $this->service->schedule($this->terms('3000', '1', AmortizationMethod::Annuity, 3), CarbonImmutable::parse('2026-01-31'));
        $this->assertSame(['2026-02-28', '2026-03-31', '2026-04-30'], array_column($s['rows'], 'due_date'));
    }

    public function test_weekly_and_biweekly_due_dates(): void
    {
        $w = $this->service->schedule($this->terms('1000', '1', AmortizationMethod::Flat, 2, [], 'monthly', RepaymentFrequency::Weekly), CarbonImmutable::parse('2026-03-02'));
        $this->assertSame(['2026-03-09', '2026-03-16'], array_column($w['rows'], 'due_date'));

        $b = $this->service->schedule($this->terms('1000', '1', AmortizationMethod::Flat, 2, [], 'monthly', RepaymentFrequency::Biweekly), CarbonImmutable::parse('2026-03-02'));
        $this->assertSame(['2026-03-16', '2026-03-30'], array_column($b['rows'], 'due_date'));
    }

    public function test_rate_conversion_is_proportional(): void
    {
        $calc = new InterestCalculator;
        $this->assertSame('0.05', $calc->periodRate('5', 'monthly', RepaymentFrequency::Monthly));
        $this->assertSame('0.01', $calc->periodRate('12', 'annual', RepaymentFrequency::Monthly));
        $this->assertSame(Money::round(Money::div('0.6', '52'), 10), Money::round($calc->periodRate('5', 'monthly', RepaymentFrequency::Weekly), 10));
    }

    public function test_single_installment(): void
    {
        $s = $this->service->schedule($this->terms('5000', '10', AmortizationMethod::Annuity, 1), CarbonImmutable::parse('2026-01-01'));
        $this->assertSame('5500.00', $s['first_installment']);
    }

    public function test_invalid_terms_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->terms('0', '5', AmortizationMethod::Annuity, 12);
    }

    public function test_installment_totals_are_consistent_for_all_methods(): void
    {
        foreach (AmortizationMethod::cases() as $method) {
            foreach ([1, 7, 24] as $n) {
                $s = $this->service->schedule($this->terms('17345.67', '4.25', $method, $n, [
                    ['name' => 'Taxa', 'type' => 'fixed', 'value' => '99.99', 'charge' => 'financed'],
                ]), CarbonImmutable::parse('2026-05-10'));

                $sumPrincipal = Money::sum($s['rows'], 'principal');
                $sumTotal = Money::sum($s['rows'], 'total');
                $this->assertTrue(Money::eq($sumPrincipal, '17345.67'), "{$method->value} n={$n}: capital");
                $this->assertTrue(Money::eq($sumTotal, Money::add($s['totals']['principal'], $s['totals']['interest'], $s['totals']['fees_financed'])), "{$method->value} n={$n}: total");
                foreach ($s['rows'] as $row) {
                    $this->assertTrue(Money::gte($row['principal'], '0'), "{$method->value} n={$n}: capital negativo");
                    $this->assertTrue(Money::eq($row['total'], Money::add($row['principal'], $row['interest'], $row['fee'])));
                }
            }
        }
    }
}
