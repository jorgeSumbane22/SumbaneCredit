<?php

namespace Tests\Unit;

use App\Models\LoanInstallment;
use App\Services\Finance\PaymentAllocator;
use App\Services\Finance\PenaltyCalculator;
use App\Services\Finance\SettlementCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PaymentAllocatorTest extends TestCase
{
    private function installment(int $n, string $due, string $principal, string $interest, string $fee = '0', string $penalty = '0'): LoanInstallment
    {
        $i = new LoanInstallment;
        $i->forceFill([
            'id' => $n, 'number' => $n, 'due_date' => $due, 'opening_balance' => '0',
            'principal_due' => $principal, 'interest_due' => $interest, 'fee_due' => $fee, 'penalty_due' => $penalty,
        ]);
        $i->recalculate();

        return $i;
    }

    public function test_allocates_penalty_fee_interest_then_principal_oldest_first(): void
    {
        $a = $this->installment(1, '2026-01-01', '100', '20', '5', '10');
        $b = $this->installment(2, '2026-02-01', '100', '15');

        $result = (new PaymentAllocator)->allocate([$a, $b], '150');

        $this->assertCount(2, $result['allocations']);
        $first = $result['allocations'][0];
        $this->assertSame(['10.00', '5.00', '20.00', '100.00', '135.00'], [$first['penalty'], $first['fee'], $first['interest'], $first['principal'], $first['total']]);
        $second = $result['allocations'][1];
        $this->assertSame('15.00', $second['interest']);   // juros antes do capital
        $this->assertSame('0.00', $second['principal']);
        $this->assertSame('0.00', $result['remaining']);
    }

    public function test_partial_payment_updates_installment_state(): void
    {
        $a = $this->installment(1, '2026-01-01', '100', '20');
        $result = (new PaymentAllocator)->allocate([$a], '50');
        $a->applyAllocation($result['allocations'][0]);

        $this->assertSame('partial', $a->status->value);
        $this->assertSame('70.00', $a->outstandingTotal());
        $this->assertSame('20.00', $a->interest_paid);
        $this->assertSame('30.00', $a->principal_paid);
    }

    public function test_full_payment_marks_installment_paid_and_reversal_restores_it(): void
    {
        $a = $this->installment(1, '2026-01-01', '100', '20');
        $alloc = (new PaymentAllocator)->allocate([$a], '120')['allocations'][0];
        $a->applyAllocation($alloc);
        $this->assertSame('paid', $a->status->value);

        $a->applyAllocation($alloc, -1);
        $this->assertSame('pending', $a->status->value);
        $this->assertSame('120.00', $a->outstandingTotal());
    }

    public function test_overpayment_returns_remaining(): void
    {
        $a = $this->installment(1, '2026-01-01', '100', '20');
        $result = (new PaymentAllocator)->allocate([$a], '200');
        $this->assertSame('80.00', $result['remaining']);
    }

    public function test_custom_allocation_order(): void
    {
        $a = $this->installment(1, '2026-01-01', '100', '20', '0', '10');
        $result = (new PaymentAllocator)->allocate([$a], '100', ['principal', 'interest', 'fee', 'penalty']);
        $this->assertSame('100.00', $result['allocations'][0]['principal']);
        $this->assertSame('0.00', $result['allocations'][0]['penalty']);
    }

    public function test_settlement_waives_future_interest_only(): void
    {
        $items = [
            $this->installment(1, '2026-01-10', '100', '30'),  // vencida
            $this->installment(2, '2026-02-10', '100', '20'),  // período em curso
            $this->installment(3, '2026-03-10', '100', '10'),  // futura
        ];
        $asOf = CarbonImmutable::parse('2026-01-20');

        $waive = (new SettlementCalculator)->quote($items, $asOf, 'waive_future_interest');
        $this->assertSame('300.00', $waive['principal']);
        $this->assertSame('50.00', $waive['interest']);
        $this->assertSame('10.00', $waive['interest_waived']);
        $this->assertSame('350.00', $waive['total']);
        $this->assertSame([3 => '10.00'], $waive['waivers']);

        $full = (new SettlementCalculator)->quote($items, $asOf, 'full_contractual');
        $this->assertSame('360.00', $full['total']);
        $this->assertSame('0.00', $full['interest_waived']);
    }

    public function test_late_fee_respects_grace_period_and_type(): void
    {
        $calc = new PenaltyCalculator;
        $i = $this->installment(1, '2026-01-10', '100', '20');

        $this->assertSame('0.00', $calc->lateFee($i, 'fixed', '50', 5, CarbonImmutable::parse('2026-01-15')));
        $this->assertSame('50.00', $calc->lateFee($i, 'fixed', '50', 5, CarbonImmutable::parse('2026-01-16')));
        $this->assertSame('6.00', $calc->lateFee($i, 'percent', '5', 0, CarbonImmutable::parse('2026-01-11')));
        $this->assertSame('0.00', $calc->lateFee($i, 'none', '5', 0, CarbonImmutable::parse('2026-03-01')));
        $this->assertSame(6, $calc->daysOverdue($i, CarbonImmutable::parse('2026-01-16')));
    }
}
