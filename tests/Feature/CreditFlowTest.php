<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Disbursement;
use App\Models\FinancialTransaction;
use App\Models\LoanApplication;
use App\Services\Credit\LoanApplicationService;
use App\Services\LedgerService;
use App\Services\Loans\DisbursementService;
use App\Services\Loans\LoanService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CreditFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_simulator_uses_financial_engine(): void
    {
        $product = $this->product(['interest_rate' => 5, 'amortization_methods' => ['annuity']]);

        $this->postJson(route('public.simulate'), ['product_id' => $product->id, 'amount' => '10000', 'term' => 12, 'frequency' => 'monthly', 'method' => 'annuity'])
            ->assertOk()
            ->assertJsonPath('installment', '1.128,25 MZN')
            ->assertJsonCount(12, 'rows')
            ->assertJsonPath('disclaimer', fn ($v) => str_contains($v, 'Não constitui aprovação'));
    }

    public function test_simulator_rejects_values_outside_product_limits(): void
    {
        $product = $this->product(['max_amount' => 50000]);
        $this->postJson(route('public.simulate'), ['product_id' => $product->id, 'amount' => '60000', 'term' => 3])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'O montante deve estar entre'));
    }

    public function test_simulator_hides_unpublished_products(): void
    {
        $product = $this->product(['is_public' => false]);
        $this->postJson(route('public.simulate'), ['product_id' => $product->id, 'amount' => '5000', 'term' => 3])->assertNotFound();
    }

    public function test_client_submits_application_through_portal(): void
    {
        $product = $this->product();
        $client = $this->clientUser();

        $this->actingAs($client)->post(route('client.applications.store'), $this->applicationData($product, [
            'guarantors' => [['full_name' => 'Fiador Teste', 'document_number' => '123', 'phone' => '841111111']],
        ]))->assertRedirect();

        $application = LoanApplication::firstOrFail();
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertSame($client->client->id, $application->client_id);
        $this->assertSame($product->current_version_id, $application->loan_product_version_id);
        $this->assertSame('3672.09', (string) $application->simulated_installment); // 10 000 × 5% / 3 prestações
        $this->assertCount(1, $application->guarantors);
        $this->assertDatabaseHas('loan_application_reviews', ['loan_application_id' => $application->id, 'action' => 'submitted']);
    }

    public function test_application_requires_declarations_and_valid_amount(): void
    {
        $product = $this->product(['max_amount' => 20000]);
        $client = $this->clientUser();

        $this->actingAs($client)->post(route('client.applications.store'), $this->applicationData($product, ['declaration' => null]))
            ->assertSessionHasErrors('declaration');
        $this->actingAs($client)->post(route('client.applications.store'), $this->applicationData($product, ['amount' => '999999']))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('loan_applications', 0);
    }

    public function test_client_cannot_have_two_open_applications(): void
    {
        $product = $this->product();
        $client = $this->clientUser();
        $this->submitApplication($client, $product);

        $this->expectException(BusinessRuleException::class);
        $this->submitApplication($client, $product);
    }

    public function test_assessment_produces_explainable_recommendation(): void
    {
        $product = $this->product();
        $application = $this->submitApplication($this->clientUser(), $product, ['amount' => '90000', 'term' => 3]);
        $agent = $this->staff('agente-credito');

        $this->actingAs($agent)->post(route('admin.applications.start-review', $application))->assertSessionHas('success');
        $this->actingAs($agent)->post(route('admin.applications.assess', $application))->assertSessionHas('success');

        $assessment = $application->fresh()->assessments()->first();
        $this->assertSame('unfavorable', $assessment->result); // prestação ≈ 33 000 MZN > rendimento
        $this->assertNotEmpty($assessment->indicators);
        $this->assertSame($agent->id, $assessment->performed_by);
        $this->assertStringContainsString('não substitui a decisão', $assessment->explanation);
    }

    public function test_approval_by_manager_creates_loan_awaiting_contract(): void
    {
        $product = $this->product();
        $application = $this->submitApplication($this->clientUser(), $product);
        $agent = $this->staff('agente-credito');
        $gestor = $this->staff('gestor-credito');

        // Não pode ser aprovado sem análise/avaliação
        $this->actingAs($gestor)->post(route('admin.applications.approve', $application), ['approved_amount' => 10000, 'approved_term' => 3])
            ->assertSessionHas('error');

        $this->actingAs($agent)->post(route('admin.applications.start-review', $application));
        $this->actingAs($agent)->post(route('admin.applications.assess', $application));
        $this->actingAs($agent)->post(route('admin.applications.forward', $application))->assertSessionHas('success');

        $this->actingAs($gestor)->post(route('admin.applications.approve', $application), [
            'approved_amount' => 8000, 'approved_term' => 3, 'decision_notes' => 'Ok',
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Approved, $application->status);
        $this->assertSame($gestor->id, $application->decided_by);
        $loan = $application->loan;
        $this->assertSame(LoanStatus::AwaitingContract, $loan->status);
        $this->assertSame('8000.00', (string) $loan->principal_amount);
        $this->assertSame(0, $loan->allInstallments()->count(), 'O plano só é gerado no desembolso');
        $this->assertSame(0, FinancialTransaction::count(), 'Aprovação não gera movimentos financeiros');
    }

    public function test_approved_amount_cannot_exceed_requested(): void
    {
        $product = $this->product();
        $application = $this->submitApplication($this->clientUser(), $product);
        $agent = $this->staff('agente-credito');
        $this->actingAs($agent)->post(route('admin.applications.start-review', $application));
        $this->actingAs($agent)->post(route('admin.applications.assess', $application));

        $this->actingAs($this->staff('gestor-credito'))->post(route('admin.applications.approve', $application), ['approved_amount' => 20000, 'approved_term' => 3])
            ->assertSessionHas('error');
        $this->assertTrue($application->fresh()->status->isOpen());
    }

    public function test_rejection_records_reason_and_blocks_further_transitions(): void
    {
        $product = $this->product();
        $application = $this->submitApplication($this->clientUser(), $product);
        $gestor = $this->staff('gestor-credito');

        $this->actingAs($gestor)->post(route('admin.applications.reject', $application), ['reason' => 'Capacidade de pagamento insuficiente.'])
            ->assertSessionHas('success');
        $application->refresh();
        $this->assertSame(ApplicationStatus::Rejected, $application->status);
        $this->assertNull($application->loan);

        // Transição inválida após decisão
        $this->actingAs($this->staff('agente-credito'))->post(route('admin.applications.forward', $application))->assertForbidden();
        $this->expectException(BusinessRuleException::class);
        Auth::guard('web')->setUser($gestor);
        app(LoanApplicationService::class)->approve($application, $gestor, '10000', 3, null);
    }

    public function test_information_request_and_client_response(): void
    {
        $product = $this->product();
        $client = $this->clientUser();
        $application = $this->submitApplication($client, $product);
        $agent = $this->staff('agente-credito');

        $this->actingAs($agent)->post(route('admin.applications.request-info', $application), ['message' => 'Envie o extracto bancário dos últimos 3 meses.']);
        $this->assertSame(ApplicationStatus::PendingDocuments, $application->fresh()->status);

        $this->actingAs($client)->post(route('client.applications.provide-info', $application), ['message' => 'Segue a informação pedida.'])
            ->assertSessionHas('success');
        $this->assertSame(ApplicationStatus::UnderReview, $application->fresh()->status);
    }

    public function test_client_can_cancel_open_application(): void
    {
        $client = $this->clientUser();
        $application = $this->submitApplication($client, $this->product());
        $this->actingAs($client)->post(route('client.applications.cancel', $application), ['reason' => 'Desisti'])->assertSessionHas('success');
        $this->assertSame(ApplicationStatus::Cancelled, $application->fresh()->status);
    }

    public function test_disbursement_requires_formalized_contract(): void
    {
        $loan = $this->approvedLoan($this->clientUser(), $this->product());
        $agent = $this->staff('agente-credito');

        $this->actingAs($agent)->post(route('admin.loans.disbursements.store', $loan), ['method' => 'mpesa', 'destination_account' => '841234567'])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('disbursements', 0);

        $this->actingAs($agent)->post(route('admin.loans.formalize', $loan), ['contract_signed_at' => today()->toDateString()])->assertSessionHas('success');
        $this->assertSame(LoanStatus::Contracted, $loan->fresh()->status);
        $this->actingAs($agent)->post(route('admin.loans.disbursements.store', $loan), ['method' => 'mpesa', 'destination_account' => '841234567'])
            ->assertRedirect();
        $this->assertDatabaseCount('disbursements', 1);
    }

    public function test_duplicate_disbursements_are_blocked(): void
    {
        $loan = $this->approvedLoan($this->clientUser(), $this->product());
        $agent = $this->staff('agente-credito');
        Auth::guard('web')->setUser($agent);
        app(LoanService::class)->formalize($loan, $agent, today()->toDateString(), null, null);
        app(DisbursementService::class)->request($loan->fresh(), $agent, ['method' => 'cash']);

        $this->actingAs($agent)->post(route('admin.loans.disbursements.store', $loan), ['method' => 'cash'])->assertSessionHas('error');
        $this->assertSame(1, Disbursement::where('loan_id', $loan->id)->count());
    }

    public function test_disbursement_must_be_authorized_by_another_user(): void
    {
        $loan = $this->approvedLoan($this->clientUser(), $this->product());
        $gestor = $this->staff('gestor-credito');
        Auth::guard('web')->setUser($gestor);
        app(LoanService::class)->formalize($loan, $gestor, today()->toDateString(), null, null);
        $d = app(DisbursementService::class)->request($loan->fresh(), $gestor, ['method' => 'cash']);

        $this->actingAs($gestor)->post(route('admin.disbursements.authorize', $d))->assertSessionHas('error');
        $this->actingAs($this->otherStaff('gestor-credito'))->post(route('admin.disbursements.authorize', $d))->assertSessionHas('success');
        $this->assertSame('authorized', $d->fresh()->status->value);
    }

    public function test_loan_is_active_only_after_disbursement_is_confirmed(): void
    {
        $product = $this->product(['fees' => [['name' => 'Comissão', 'type' => 'percent', 'value' => 2, 'charge' => 'upfront']]]);
        $loan = $this->approvedLoan($this->clientUser(), $product);
        $agent = $this->staff('agente-credito');
        Auth::guard('web')->setUser($agent);
        app(LoanService::class)->formalize($loan, $agent, today()->toDateString(), null, null);
        $d = app(DisbursementService::class)->request($loan->fresh(), $agent, ['method' => 'mpesa', 'destination_account' => '841234567']);
        app(DisbursementService::class)->authorize($d, $this->staff('gestor-credito'));
        $this->assertSame(LoanStatus::Contracted, $loan->fresh()->status, 'Autorização não activa o empréstimo');
        $this->assertSame('9800.00', (string) $d->fresh()->net_amount);

        $caixa = $this->staff('caixa');
        // Referência obrigatória para M-Pesa
        $this->actingAs($caixa)->post(route('admin.disbursements.complete', $d), ['disbursed_at' => today()->toDateString()])->assertSessionHas('error');
        $this->actingAs($caixa)->post(route('admin.disbursements.complete', $d), ['disbursed_at' => today()->toDateString(), 'reference' => 'MP123456'])
            ->assertSessionHas('success');

        $loan->refresh();
        $this->assertSame(LoanStatus::Active, $loan->status);
        $this->assertSame(3, $loan->currentInstallments()->count());
        $this->assertSame('10000.00', (string) $loan->principal_outstanding);
        $ledger = app(LedgerService::class)->balances($loan);
        $this->assertTrue(Money::eq($ledger['total'], $loan->total_outstanding));
        $this->assertTrue(Money::eq($ledger['principal'], '10000'));
        $this->assertDatabaseHas('financial_transactions', ['loan_id' => $loan->id, 'type' => 'disbursement_cash_out', 'amount' => '9800.00']);
    }

    public function test_rejected_or_cancelled_loans_cannot_be_disbursed(): void
    {
        $loan = $this->approvedLoan($this->clientUser(), $this->product());
        $gestor = $this->staff('gestor-credito');
        $this->actingAs($gestor)->post(route('admin.loans.cancel', $loan), ['reason' => 'Cliente desistiu'])->assertSessionHas('success');

        $this->expectException(BusinessRuleException::class);
        Auth::guard('web')->setUser($gestor);
        app(DisbursementService::class)->request($loan->fresh(), $gestor, ['method' => 'cash']);
    }

    public function test_product_changes_create_new_version_without_affecting_contracts(): void
    {
        $product = $this->product(['interest_rate' => 5]);
        $loan = $this->approvedLoan($this->clientUser(), $product);
        $admin = $this->staff('admin');
        $v = $product->currentVersion;

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'code' => $product->code, 'name' => $product->name, 'description' => $product->description, 'status' => 'active', 'is_public' => 1,
            'min_amount' => $v->min_amount, 'max_amount' => $v->max_amount, 'min_term' => $v->min_term, 'max_term' => $v->max_term,
            'interest_rate' => 7, 'rate_period' => 'monthly', 'allowed_frequencies' => ['monthly'], 'amortization_methods' => ['annuity'],
            'penalty_type' => 'none', 'penalty_grace_days' => 0, 'early_settlement_rule' => 'waive_future_interest',
        ])->assertRedirect();

        $product->refresh();
        $this->assertSame(2, $product->currentVersion->version);
        $this->assertSame('7.0000', (string) $product->currentVersion->interest_rate);
        $this->assertSame('5.0000', (string) $loan->fresh()->interest_rate, 'O contrato mantém a taxa acordada');
        $this->assertSame($v->id, $loan->fresh()->loan_product_version_id);
    }
}
