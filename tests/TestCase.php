<?php

namespace Tests;

use App\Models\Client;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\Credit\CreditAssessmentService;
use App\Services\Credit\LoanApplicationService;
use App\Services\Credit\LoanProductService;
use App\Services\Loans\DisbursementService;
use App\Services\Loans\LoanService;
use App\Services\Payments\PaymentService;
use App\Support\PermissionSync;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, User> */
    protected array $staffUsers = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['sumbanecredit.sms.driver' => 'null']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    protected function seedPermissions(): void
    {
        PermissionSync::run();
    }

    protected function staff(string $role): User
    {
        if (! isset($this->staffUsers[$role])) {
            $this->seedPermissions();
            $this->staffUsers[$role] = User::factory()->staff($role)->create(['name' => 'Teste '.$role]);
        }

        return $this->staffUsers[$role];
    }

    /** Segundo utilizador do mesmo perfil (segregação de funções). */
    protected function otherStaff(string $role): User
    {
        $this->seedPermissions();

        return User::factory()->staff($role)->create();
    }

    protected function clientUser(array $clientAttributes = []): User
    {
        $this->seedPermissions();
        $client = Client::factory()->withUser()->create($clientAttributes);
        $client->addresses()->create(['province' => 'Maputo Cidade', 'is_primary' => true]);
        $client->employment()->create(['employment_type' => 'employed', 'monthly_income' => '30000', 'monthly_expenses' => '8000']);

        return $client->user->fresh('client');
    }

    protected function atDate(string $date): void
    {
        $at = CarbonImmutable::parse($date)->setTime(10, 0);
        Carbon::setTestNow($at);
        CarbonImmutable::setTestNow($at);
    }

    protected function product(array $overrides = []): LoanProduct
    {
        $data = array_replace_recursive([
            'code' => 'T'.strtoupper(Str::random(5)), 'name' => 'Produto Teste '.Str::random(4), 'description' => 'Produto de teste',
            'status' => 'active', 'is_public' => true,
            'min_amount' => 1000, 'max_amount' => 100000, 'min_term' => 1, 'max_term' => 24,
            'interest_rate' => 5, 'rate_period' => 'monthly',
            'allowed_frequencies' => ['monthly'], 'amortization_methods' => ['annuity', 'flat', 'equal_principal'],
            'fees' => [],
            'penalty_type' => 'fixed', 'penalty_value' => 100, 'penalty_grace_days' => 3,
            'early_settlement_rule' => 'waive_future_interest',
            'eligibility' => ['min_age' => 18, 'max_active_loans' => 5],
        ], $overrides);
        if (array_key_exists('fees', $overrides)) {
            $data['fees'] = $overrides['fees'];
        }

        return app(LoanProductService::class)->create($data, $this->staff('admin'))->fresh('currentVersion');
    }

    protected function applicationData(LoanProduct $product, array $overrides = []): array
    {
        return array_merge([
            'loan_product_id' => $product->id, 'amount' => '10000', 'term' => 3, 'frequency' => 'monthly', 'method' => 'annuity',
            'purpose' => 'Compra de mercadoria para o negócio', 'monthly_income' => '30000', 'other_income' => '0',
            'monthly_expenses' => '8000', 'existing_obligations' => '0', 'employment_type' => 'employed',
            'declaration' => true, 'consent_data_processing' => true, 'consent_credit_check' => true,
        ], $overrides);
    }

    protected function submitApplication(User $clientUser, LoanProduct $product, array $overrides = []): LoanApplication
    {
        Auth::guard('web')->setUser($clientUser);

        return app(LoanApplicationService::class)->submit($clientUser->client, $this->applicationData($product, $overrides), $clientUser);
    }

    protected function approvedLoan(User $clientUser, LoanProduct $product, array $overrides = []): Loan
    {
        $application = $this->submitApplication($clientUser, $product, $overrides);
        $agent = $this->staff('agente-credito');
        Auth::guard('web')->setUser($agent);
        app(LoanApplicationService::class)->startReview($application, $agent);
        app(CreditAssessmentService::class)->assess($application->fresh(), $agent);
        $gestor = $this->staff('gestor-credito');
        Auth::guard('web')->setUser($gestor);

        return app(LoanApplicationService::class)->approve($application->fresh(), $gestor, (string) ($overrides['amount'] ?? '10000'), (int) ($overrides['term'] ?? 3), null);
    }

    /** Pedido → aprovação → contrato → desembolso efectuado na data indicada. */
    protected function activeLoan(User $clientUser, LoanProduct $product, string $disbursedAt = '2026-01-15', array $overrides = []): Loan
    {
        $this->atDate($disbursedAt);
        $loan = $this->approvedLoan($clientUser, $product, $overrides);
        $agent = $this->staff('agente-credito');
        Auth::guard('web')->setUser($agent);
        app(LoanService::class)->formalize($loan, $agent, $disbursedAt, null, null);
        $d = app(DisbursementService::class)->request($loan->fresh(), $agent, ['method' => 'mpesa', 'destination_account' => '841234567']);
        app(DisbursementService::class)->authorize($d, $this->staff('gestor-credito'));
        app(DisbursementService::class)->complete($d->fresh(), $this->staff('caixa'), ['reference' => 'DSB'.Str::random(8), 'disbursed_at' => $disbursedAt]);
        Auth::guard('web')->logout();

        return $loan->fresh();
    }

    protected function pay(Loan $loan, string $amount, string $date, ?string $reference = null, string $method = 'mpesa'): \App\Models\Payment
    {
        $this->atDate($date);
        $caixa = $this->staff('caixa');
        Auth::guard('web')->setUser($caixa);
        $service = app(PaymentService::class);
        $payment = $service->register($loan->fresh(), $caixa, [
            'amount' => $amount, 'method' => $method, 'reference' => $method === 'cash' ? null : ($reference ?? 'REF'.Str::random(10)), 'paid_at' => $date,
        ]);

        return $service->confirm($payment, $caixa);
    }

    protected function pendingDisbursement(Loan $loan): ?Disbursement
    {
        return Disbursement::where('loan_id', $loan->id)->where('status', 'pending')->first();
    }
}
