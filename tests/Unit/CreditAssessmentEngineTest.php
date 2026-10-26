<?php

namespace Tests\Unit;

use App\Enums\RepaymentFrequency;
use App\Services\Finance\CreditAssessmentEngine;
use PHPUnit\Framework\TestCase;

class CreditAssessmentEngineTest extends TestCase
{
    private function input(array $overrides = []): array
    {
        return array_merge([
            'monthly_income' => '30000', 'other_income' => '0', 'monthly_expenses' => '10000',
            'existing_obligations' => '0', 'installment' => '5000', 'frequency' => RepaymentFrequency::Monthly,
            'principal' => '50000', 'outstanding_exposure' => '0', 'active_loans' => 0,
            'worst_days_overdue' => 0, 'previous_loans' => 1,
        ], $overrides);
    }

    public function test_favorable_when_all_indicators_within_limits(): void
    {
        $r = (new CreditAssessmentEngine)->assess($this->input(), []);
        $this->assertSame('favorable', $r['result']);
        $this->assertNotEmpty($r['indicators']);
        $this->assertStringContainsString('não substitui a decisão', $r['explanation']);
    }

    public function test_unfavorable_when_installment_exceeds_income_capacity(): void
    {
        $r = (new CreditAssessmentEngine)->assess($this->input(['installment' => '20000']), []);
        $this->assertSame('unfavorable', $r['result']);
        $statuses = array_column($r['indicators'], 'status', 'key');
        $this->assertSame('unfavorable', $statuses['installment_to_income']);
    }

    public function test_review_when_no_internal_history(): void
    {
        $r = (new CreditAssessmentEngine)->assess($this->input(['previous_loans' => 0, 'worst_days_overdue' => null]), []);
        $this->assertSame('review', $r['result']);
    }

    public function test_configurable_thresholds_change_result(): void
    {
        $engine = new CreditAssessmentEngine;
        $input = $this->input(['installment' => '10000']); // 33% do rendimento
        $this->assertSame('review', $engine->assess($input, [])['result']);
        $this->assertSame('favorable', $engine->assess($input, ['installment_to_income_favorable' => 35, 'debt_service_favorable' => 50])['result']);
    }

    public function test_weekly_installment_is_converted_to_monthly_equivalent(): void
    {
        $engine = new CreditAssessmentEngine;
        $this->assertSame('4333.33', $engine->monthlyEquivalent('1000', RepaymentFrequency::Weekly));
    }

    public function test_zero_income_is_unfavorable(): void
    {
        $r = (new CreditAssessmentEngine)->assess($this->input(['monthly_income' => '0']), []);
        $this->assertSame('unfavorable', $r['result']);
    }
}
