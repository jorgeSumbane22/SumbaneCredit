<?php

namespace Tests\Feature;

use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\FinancialTransaction;
use App\Models\Payment;
use App\Services\LedgerService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\ReconciliationService;
use App\Services\Payments\SettlementService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function loan(array $productOverrides = [], array $appOverrides = [])
    {
        $product = $this->product($productOverrides + ['amortization_methods' => ['equal_principal', 'annuity', 'flat']]);

        // 9 000 MZN, 3 prestações mensais, 5% a.m., amortização constante: 3 000 capital por prestação
        return $this->activeLoan($this->clientUser(), $product, '2026-01-15', $appOverrides + ['amount' => '9000', 'term' => 3, 'method' => 'equal_principal']);
    }

    private function assertReconciled($loan): void
    {
        $check = app(ReconciliationService::class)->checkLoan($loan->fresh());
        $this->assertTrue($check['matched'], 'Reconciliação falhou: '.json_encode($check['checks']));
    }

    public function test_schedule_generated_from_disbursement_date(): void
    {
        $loan = $this->loan();
        $installments = $loan->currentInstallments()->get();

        $this->assertSame(['2026-02-15', '2026-03-15', '2026-04-15'], $installments->map(fn ($i) => $i->due_date->toDateString())->all());
        $this->assertSame(['3450.00', '3300.00', '3150.00'], $installments->pluck('total_due')->map(fn ($v) => (string) $v)->all());
        $this->assertSame('9900.00', (string) $loan->total_outstanding);
        $this->assertReconciled($loan);
    }

    public function test_full_installment_payment(): void
    {
        $loan = $this->loan();
        $payment = $this->pay($loan, '3450', '2026-02-15');

        $first = $loan->currentInstallments()->first();
        $this->assertSame(InstallmentStatus::Paid, $first->status);
        $this->assertSame(PaymentStatus::Confirmed, $payment->status);
        $this->assertNotNull($payment->receipt_number);
        $loan->refresh();
        $this->assertSame('6450.00', (string) $loan->total_outstanding);
        $this->assertSame('6000.00', (string) $loan->principal_outstanding);
        $this->assertSame('3450.00', (string) $loan->total_paid);
        $this->assertSame('2026-03-15', $loan->next_due_date->toDateString());
        $this->assertReconciled($loan);
    }

    public function test_partial_payment_allocates_interest_before_principal(): void
    {
        $loan = $this->loan();
        $this->pay($loan, '1000', '2026-02-10');

        $first = $loan->currentInstallments()->first();
        $this->assertSame(InstallmentStatus::Partial, $first->status);
        $this->assertSame('450.00', (string) $first->interest_paid);
        $this->assertSame('550.00', (string) $first->principal_paid);
        $this->assertSame('2450.00', $first->outstandingTotal());
        $this->assertSame('8900.00', (string) $loan->fresh()->total_outstanding);
        $this->assertReconciled($loan);
    }

    public function test_advance_payment_covers_following_installments(): void
    {
        $loan = $this->loan();
        $this->pay($loan, '5000', '2026-02-01');

        [$i1, $i2] = $loan->currentInstallments()->get()->all();
        $this->assertSame(InstallmentStatus::Paid, $i1->status);
        $this->assertSame(InstallmentStatus::Partial, $i2->status);
        $this->assertSame('1550.00', (string) $i2->total_paid);
        $this->assertSame('0', (string) $loan->fresh()->days_past_due);
        $this->assertReconciled($loan);
    }

    public function test_paying_everything_settles_the_loan(): void
    {
        $loan = $this->loan();
        $this->pay($loan, '3450', '2026-02-15');
        $this->pay($loan, '3300', '2026-03-15');
        $this->pay($loan, '3150', '2026-04-15', null, 'cash');

        $loan->refresh();
        $this->assertSame(LoanStatus::Settled, $loan->status);
        $this->assertSame('0.00', (string) $loan->total_outstanding);
        $this->assertNotNull($loan->settled_at);
        $this->assertReconciled($loan);
    }

    public function test_overpayment_is_rejected(): void
    {
        $loan = $this->loan();
        $this->expectException(BusinessRuleException::class);
        $this->pay($loan, '10000', '2026-02-15');
    }

    public function test_duplicate_reference_is_rejected(): void
    {
        $loan = $this->loan();
        $this->pay($loan, '100', '2026-02-01', 'ABC123');

        try {
            $this->pay($loan, '100', '2026-02-02', 'abc123');
            $this->fail('Referência duplicada aceite');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Já existe um pagamento', $e->getMessage());
        }
        $this->assertSame(1, Payment::where('loan_id', $loan->id)->count());
    }

    public function test_repeated_submission_with_same_idempotency_key_is_ignored(): void
    {
        $loan = $this->loan();
        $this->atDate('2026-02-01');
        $caixa = $this->staff('caixa');
        $key = (string) Str::uuid();
        $data = ['loan_id' => $loan->id, 'amount' => '500', 'method' => 'cash', 'paid_at' => '2026-02-01', 'idempotency_key' => $key];

        $this->actingAs($caixa)->post(route('admin.payments.store'), $data)->assertRedirect();
        $this->actingAs($caixa)->post(route('admin.payments.store'), $data)->assertSessionHas('error');
        $this->assertSame(1, Payment::where('loan_id', $loan->id)->count());
    }

    public function test_payment_cannot_be_confirmed_twice(): void
    {
        $loan = $this->loan();
        $payment = $this->pay($loan, '500', '2026-02-01');

        $this->expectException(BusinessRuleException::class);
        app(PaymentService::class)->confirm($payment, $this->staff('caixa'));
    }

    public function test_reversal_creates_compensating_entries_and_restores_balances(): void
    {
        $loan = $this->loan();
        $payment = $this->pay($loan, '3450', '2026-02-15');
        $before = FinancialTransaction::where('loan_id', $loan->id)->count();

        $this->atDate('2026-02-20');
        $this->actingAs($this->staff('contabilista'))
            ->post(route('admin.payments.reverse', $payment), ['reason' => 'Referência não consta do extracto bancário'])
            ->assertSessionHas('success');

        $payment->refresh();
        $loan->refresh();
        $this->assertSame(PaymentStatus::Reversed, $payment->status);
        $this->assertNotNull($payment->reversal);
        $this->assertSame('9900.00', (string) $loan->total_outstanding);
        $this->assertSame(InstallmentStatus::Pending, $loan->currentInstallments()->first()->status);
        $this->assertGreaterThan($before, FinancialTransaction::where('loan_id', $loan->id)->count(), 'O estorno não apaga movimentos; acrescenta compensatórios');
        $this->assertTrue($payment->allocations()->first()->is_reversed);
        $this->assertReconciled($loan);
    }

    public function test_reversals_follow_lifo_order(): void
    {
        $loan = $this->loan();
        $first = $this->pay($loan, '1000', '2026-02-01');
        $this->pay($loan, '1000', '2026-02-05');

        $this->expectException(BusinessRuleException::class);
        Auth::guard('web')->setUser($this->staff('contabilista'));
        app(PaymentService::class)->reverse($first, $this->staff('contabilista'), 'Teste de ordem inversa');
    }

    public function test_reversal_reopens_settled_loan(): void
    {
        $loan = $this->loan();
        $this->pay($loan, '3450', '2026-02-15');
        $this->pay($loan, '3300', '2026-03-15');
        $last = $this->pay($loan, '3150', '2026-04-15');
        $this->assertSame(LoanStatus::Settled, $loan->fresh()->status);

        Auth::guard('web')->setUser($this->staff('contabilista'));
        app(PaymentService::class)->reverse($last, $this->staff('contabilista'), 'Valor devolvido ao cliente');
        $this->assertSame(LoanStatus::Active, $loan->fresh()->status);
        $this->assertReconciled($loan);
    }

    public function test_early_settlement_waives_future_interest(): void
    {
        $loan = $this->loan();
        $this->atDate('2026-02-20'); // 1.ª vencida, 2.ª em curso, 3.ª futura

        $quote = app(SettlementService::class)->quote($loan->fresh(), CarbonImmutable::parse('2026-02-20'));
        // Capital 9 000 + juros da 1.ª (450) + juros do período em curso (300); dispensa juros da 3.ª (150)
        $this->assertSame('9750.00', $quote['total']);
        $this->assertSame('150.00', $quote['interest_waived']);

        $caixa = $this->staff('caixa');
        Auth::guard('web')->setUser($caixa);
        $payment = app(SettlementService::class)->register($loan->fresh(), $caixa, ['method' => 'cash', 'paid_at' => '2026-02-20']);
        app(PaymentService::class)->confirm($payment, $caixa);

        $loan->refresh();
        $this->assertSame(LoanStatus::Settled, $loan->status);
        $this->assertSame('150.00', (string) $loan->currentInstallments()->get()->last()->interest_waived);
        $this->assertDatabaseHas('financial_transactions', ['loan_id' => $loan->id, 'type' => 'interest_waiver', 'amount' => '150.00']);
        $this->assertReconciled($loan);

        // Estorno da liquidação repõe os juros dispensados
        app(PaymentService::class)->reverse($payment->fresh(), $this->staff('contabilista'), 'Cheque devolvido');
        $this->assertSame('9900.00', (string) $loan->fresh()->total_outstanding);
        $this->assertReconciled($loan);
    }

    public function test_client_proof_is_pending_until_staff_confirmation(): void
    {
        $client = $this->clientUser();
        $loan = $this->activeLoan($client, $this->product(), '2026-01-15', ['amount' => '9000', 'term' => 3]);
        $this->atDate('2026-02-14');

        $this->actingAs($client)->post(route('client.payments.store'), [
            'loan_id' => $loan->id, 'amount' => '1000', 'method' => 'mpesa', 'reference' => 'MPX123',
            'paid_at' => '2026-02-14', 'proof' => UploadedFile::fake()->create('comprovativo.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $payment = Payment::where('loan_id', $loan->id)->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('client_portal', $payment->source);
        $this->assertSame((string) $loan->total_outstanding, (string) $loan->fresh()->total_outstanding, 'Comprovativo não altera saldos');

        $this->actingAs($this->staff('caixa'))->post(route('admin.payments.confirm', $payment))->assertSessionHas('success');
        $this->assertSame(PaymentStatus::Confirmed, $payment->fresh()->status);
    }

    public function test_client_cannot_pay_another_clients_loan(): void
    {
        $owner = $this->clientUser();
        $loan = $this->activeLoan($owner, $this->product());
        $intruder = $this->clientUser();

        $this->actingAs($intruder)->post(route('client.payments.store'), [
            'loan_id' => $loan->id, 'amount' => '100', 'method' => 'mpesa', 'reference' => 'X1', 'paid_at' => '2026-01-20',
            'proof' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
        ])->assertNotFound();
        $this->assertSame(0, Payment::count());
    }

    public function test_confirmed_payments_and_ledger_cannot_be_deleted_or_altered(): void
    {
        $loan = $this->loan();
        $payment = $this->pay($loan, '500', '2026-02-01');

        try {
            $payment->delete();
            $this->fail('Pagamento apagado');
        } catch (LogicException) {
        }
        $tx = FinancialTransaction::where('loan_id', $loan->id)->first();
        try {
            $tx->update(['amount' => '1.00']);
            $this->fail('Movimento alterado');
        } catch (LogicException) {
        }
        $this->expectException(LogicException::class);
        $tx->delete();
    }

    public function test_method_period_reconciliation(): void
    {
        $loan = $this->loan();
        $this->pay($loan, '1000', '2026-02-01', 'R1', 'mpesa');
        $this->pay($loan, '500', '2026-02-03', 'R2', 'mpesa');
        $user = $this->staff('contabilista');

        $ok = app(ReconciliationService::class)->reconcileMethodPeriod('mpesa', '2026-02-01', '2026-02-28', '1500', $user, null);
        $this->assertSame('matched', $ok->status);
        $bad = app(ReconciliationService::class)->reconcileMethodPeriod('mpesa', '2026-02-01', '2026-02-28', '1400', $user, null);
        $this->assertSame('mismatch', $bad->status);
        $this->assertSame('-100.00', (string) $bad->difference);
    }

    public function test_ledger_balances_match_installments_after_many_operations(): void
    {
        $loan = $this->loan(['fees' => [['name' => 'Seguro', 'type' => 'fixed', 'value' => 90, 'charge' => 'financed']]]);
        $this->pay($loan, '1234.56', '2026-02-10');
        $this->pay($loan, '2500', '2026-03-01', null, 'cash');
        $p = $this->pay($loan, '777.77', '2026-03-20');
        Auth::guard('web')->setUser($this->staff('contabilista'));
        app(PaymentService::class)->reverse($p, $this->staff('contabilista'), 'Teste de consistência');

        $loan->refresh();
        $ledger = app(LedgerService::class)->balances($loan);
        $this->assertTrue(Money::eq($ledger['total'], $loan->total_outstanding));
        $this->assertReconciled($loan);
    }
}
