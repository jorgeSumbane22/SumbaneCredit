<?php

namespace Database\Seeders;

use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\CollectionCase;
use App\Models\ContactMessage;
use App\Models\CreditAssessmentRuleSet;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Collections\CollectionService;
use App\Services\Credit\CreditAssessmentService;
use App\Services\Credit\LoanApplicationService;
use App\Services\Credit\LoanProductService;
use App\Services\DocumentStorageService;
use App\Services\Loans\DisbursementService;
use App\Services\Loans\LoanMonitoringService;
use App\Services\Loans\LoanService;
use App\Services\Loans\RestructuringService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\SettlementService;
use App\Services\SupportService;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Factories\ClientFactory;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DADOS DE DEMONSTRAÇÃO — apenas para ambiente local.
 *
 * Todas as pessoas, contactos e operações são fictícias (domínio @demo.sumbanecredit.local,
 * clientes com is_demo = 1). As operações são criadas através dos serviços reais, com datas
 * simuladas, para que prestações, razão, pagamentos e saldos sejam coerentes entre si.
 */
class DemoDataSeeder extends Seeder
{
    /** @var array<string, User> */
    private array $staff = [];

    /** @var array<string, LoanProduct> */
    private array $products = [];

    private CarbonImmutable $today;

    private string $pdfPath;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Os dados de demonstração só podem ser criados em ambiente local.');
        }
        if (User::where('email', 'admin@demo.sumbanecredit.local')->exists()) {
            $this->command?->warn('Os dados de demonstração já existem. Use "php artisan migrate:fresh --seed" para recriar.');

            return;
        }

        // Durante a criação: filas síncronas e emails em memória (as notificações internas ficam registadas).
        config(['queue.default' => 'sync', 'mail.default' => 'array']);
        $this->today = CarbonImmutable::today();
        $this->pdfPath = $this->makeSamplePdf();
        $password = config('sumbanecredit.demo_password');

        $this->createStaff($password);
        $this->createProducts();
        $this->validateAssessmentRules();
        $this->createTestimonials();
        $clients = $this->createClients($password, 26);

        $this->runScenarios($clients);

        $this->travel(null);
        $this->createSupportAndMessages($clients[0]);

        Auth::guard('web')->setUser($this->staff['caixa']);
        app(LoanMonitoringService::class)->processOverdue($this->today);
        app(LoanMonitoringService::class)->sendDueReminders($this->today);
        $this->createCollectionActivity();
        Auth::guard('web')->logout();

        @unlink($this->pdfPath);
        $this->printCredentials($password);
    }

    // ------------------------------------------------------------------ infra-estrutura

    private function travel(?CarbonImmutable $date): void
    {
        $at = $date?->setTime(10, random_int(0, 59));
        Carbon::setTestNow($at);
        CarbonImmutable::setTestNow($at);
    }

    private function actingAs(User $user): void
    {
        Auth::guard('web')->setUser($user);
    }

    private function makeSamplePdf(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'scdemo').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 300 120]/Parent 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return $path;
    }

    private function samplePdf(string $name): UploadedFile
    {
        $copy = tempnam(sys_get_temp_dir(), 'scdoc').'.pdf';
        copy($this->pdfPath, $copy);

        return new UploadedFile($copy, $name, 'application/pdf', null, true);
    }

    private function ref(string $prefix = 'DEMO'): string
    {
        return $prefix.strtoupper(Str::random(9));
    }

    // ------------------------------------------------------------------ base

    private function createStaff(string $password): void
    {
        $people = [
            'super-admin' => ['Administrador Técnico (Demo)', 'superadmin'],
            'admin' => ['Carla Mabote (Demo)', 'admin'],
            'gestor-credito' => ['Rui Nhaca (Demo)', 'gestor'],
            'agente-credito' => ['Sónia Guambe (Demo)', 'agente'],
            'agente-credito-2' => ['Abel Tivane (Demo)', 'agente2'],
            'caixa' => ['Joana Maússe (Demo)', 'caixa'],
            'contabilista' => ['Luís Chambal (Demo)', 'contabilista'],
            'gestor-credito-2' => ['Marta Fumo (Demo)', 'gestor2'],
        ];
        foreach ($people as $key => [$name, $login]) {
            $role = preg_replace('/-\d$/', '', $key);
            $user = User::create([
                'name' => $name, 'email' => "{$login}@demo.sumbanecredit.local", 'phone' => '84'.random_int(1000000, 9999999),
                'password' => $password, 'status' => 'active', 'is_staff' => true,
            ]);
            $user->forceFill(['email_verified_at' => now(), 'last_login_at' => now()->subDays(random_int(0, 5))])->save();
            $user->assignRole($role);
            $this->staff[$key] = $user;
        }
    }

    private function createProducts(): void
    {
        $this->actingAs($this->staff['admin']);
        $this->travel($this->today->subMonths(14));
        $service = app(LoanProductService::class);
        $note = "\n\nProduto de demonstração — condições ilustrativas, a definir e validar pela instituição.";

        $defs = [
            'PN' => [
                'name' => 'Crédito Pequeno Negócio', 'description' => 'Financiamento para reforço de stock, equipamento e fundo de maneio de pequenos negócios formais e informais.'.$note,
                'requirements' => "Documento de identificação válido\nNUIT\nComprovativo de residência\nDescrição da actividade e registo de vendas ou extracto",
                'min_amount' => 5000, 'max_amount' => 150000, 'min_term' => 3, 'max_term' => 18, 'interest_rate' => 4.5, 'rate_period' => 'monthly',
                'allowed_frequencies' => ['monthly', 'biweekly'], 'amortization_methods' => ['annuity', 'flat'],
                'fees' => [['name' => 'Comissão de abertura', 'type' => 'percent', 'value' => 3, 'charge' => 'upfront']],
                'penalty_type' => 'percent', 'penalty_value' => 5, 'penalty_grace_days' => 3, 'early_settlement_rule' => 'waive_future_interest',
                'eligibility' => ['min_monthly_income' => 5000, 'max_active_loans' => 2, 'min_age' => 18],
            ],
            'CS' => [
                'name' => 'Crédito Salarial', 'description' => 'Crédito pessoal para trabalhadores por conta de outrem, com prestações mensais ajustadas ao salário.'.$note,
                'requirements' => "Documento de identificação válido\nNUIT\nDeclaração da entidade empregadora\nÚltimos 3 recibos de vencimento ou extracto bancário",
                'min_amount' => 3000, 'max_amount' => 200000, 'min_term' => 1, 'max_term' => 24, 'interest_rate' => 3.5, 'rate_period' => 'monthly',
                'allowed_frequencies' => ['monthly'], 'amortization_methods' => ['annuity', 'equal_principal'],
                'fees' => [['name' => 'Comissão de processamento', 'type' => 'percent', 'value' => 2, 'charge' => 'upfront'], ['name' => 'Seguro de crédito', 'type' => 'percent', 'value' => 0.5, 'charge' => 'financed']],
                'penalty_type' => 'fixed', 'penalty_value' => 250, 'penalty_grace_days' => 5, 'early_settlement_rule' => 'waive_future_interest',
                'eligibility' => ['min_monthly_income' => 8000, 'max_active_loans' => 1, 'min_age' => 18],
            ],
            'MS' => [
                'name' => 'Microcrédito Semanal Mercado', 'description' => 'Pequenos montantes com prestações semanais, pensado para vendedores de mercado.'.$note,
                'requirements' => "Documento de identificação válido\nUm fiador\nIndicação do local de venda",
                'min_amount' => 1000, 'max_amount' => 30000, 'min_term' => 4, 'max_term' => 26, 'interest_rate' => 5, 'rate_period' => 'monthly',
                'allowed_frequencies' => ['weekly'], 'amortization_methods' => ['flat'],
                'fees' => [['name' => 'Taxa de dossier', 'type' => 'fixed', 'value' => 150, 'charge' => 'upfront']],
                'penalty_type' => 'percent', 'penalty_value' => 2, 'penalty_grace_days' => 2, 'early_settlement_rule' => 'full_contractual',
                'eligibility' => ['max_active_loans' => 2, 'min_age' => 18, 'requires_guarantor' => true],
            ],
            'CA' => [
                'name' => 'Crédito Agrícola Campanha', 'description' => 'Financiamento de insumos e preparação de terras para a campanha agrícola.'.$note,
                'requirements' => "Documento de identificação válido\nComprovativo da exploração\nGarantia (equipamento, gado ou outro bem)",
                'min_amount' => 10000, 'max_amount' => 300000, 'min_term' => 6, 'max_term' => 12, 'interest_rate' => 30, 'rate_period' => 'annual',
                'allowed_frequencies' => ['monthly'], 'amortization_methods' => ['equal_principal'],
                'fees' => [['name' => 'Comissão de abertura', 'type' => 'fixed', 'value' => 500, 'charge' => 'upfront']],
                'penalty_type' => 'percent', 'penalty_value' => 3, 'penalty_grace_days' => 7, 'early_settlement_rule' => 'waive_future_interest',
                'eligibility' => ['max_active_loans' => 1, 'min_age' => 18, 'requires_collateral' => true],
            ],
        ];

        foreach ($defs as $code => $d) {
            $this->products[$code] = $service->create($d + ['code' => $code, 'status' => 'active', 'is_public' => true,
                'contract_conditions' => 'O mutuário obriga-se a utilizar o crédito para a finalidade declarada e a comunicar à instituição qualquer alteração dos seus contactos ou da sua situação financeira. (Texto de demonstração)'], $this->staff['admin']);
        }

        // Exemplo de versionamento: revisão de taxa do Crédito Pequeno Negócio há 6 meses.
        $this->travel($this->today->subMonths(6));
        $pn = $this->products['PN']->fresh('currentVersion');
        $v = $pn->currentVersion;
        $service->update($pn, [
            'code' => 'PN', 'name' => $pn->name, 'description' => $pn->description, 'requirements' => $pn->requirements,
            'contract_conditions' => $pn->contract_conditions, 'status' => 'active', 'is_public' => true,
            'min_amount' => $v->min_amount, 'max_amount' => $v->max_amount, 'min_term' => $v->min_term, 'max_term' => $v->max_term,
            'interest_rate' => 4.0, 'rate_period' => 'monthly', 'allowed_frequencies' => $v->allowed_frequencies, 'amortization_methods' => $v->amortization_methods,
            'fees' => $v->fees, 'penalty_type' => $v->penalty_type, 'penalty_value' => $v->penalty_value, 'penalty_grace_days' => $v->penalty_grace_days,
            'early_settlement_rule' => $v->early_settlement_rule, 'eligibility' => $v->eligibility_rules, 'change_notes' => 'Revisão da taxa de juro (demonstração).',
        ], $this->staff['admin']);
        $this->products['PN'] = $pn->fresh('currentVersion');
        foreach ($this->products as $code => $p) {
            $this->products[$code] = $p->fresh('currentVersion');
        }
    }

    private function validateAssessmentRules(): void
    {
        // Em demonstração, a proposta v1 é validada por um gestor (fluxo real de validação).
        $set = CreditAssessmentRuleSet::where('version', 1)->first();
        $set?->update(['is_active' => true, 'validated_by' => $this->staff['gestor-credito']->id, 'validated_at' => $this->today->subMonths(13),
            'name' => 'Parâmetros de referência (validados para demonstração)']);
    }

    private function createTestimonials(): void
    {
        foreach ([
            ['Lurdes M.', 'Vendedora, Mercado Xipamanine', 'Consegui reforçar o stock da banca e acompanhar as prestações pelo telemóvel.'],
            ['Armando C.', 'Electricista, Matola', 'A simulação mostrou exactamente o valor que ia pagar. Sem surpresas.'],
            ['Graça T.', 'Costureira, Beira', 'Fui avisada antes de cada vencimento e recebi os recibos no portal.'],
        ] as [$name, $role, $text]) {
            Testimonial::create(['name' => $name, 'role' => $role, 'content' => $text, 'is_demo' => true, 'is_active' => true]);
        }
    }

    /** @return Client[] */
    private function createClients(string $password, int $count): array
    {
        $storage = app(DocumentStorageService::class);
        $provinces = ['Maputo Cidade', 'Maputo Província', 'Gaza', 'Inhambane', 'Sofala', 'Manica', 'Nampula', 'Zambézia', 'Tete'];
        $clients = [];
        $this->actingAs($this->staff['agente-credito']);

        for ($i = 0; $i < $count; $i++) {
            $this->travel($this->today->subMonths(13)->addDays($i * 3));
            $first = ClientFactory::FIRST_NAMES[$i % count(ClientFactory::FIRST_NAMES)];
            $last = ClientFactory::LAST_NAMES[($i * 7) % count(ClientFactory::LAST_NAMES)].' '.ClientFactory::LAST_NAMES[($i * 3 + 5) % count(ClientFactory::LAST_NAMES)];
            $name = "{$first} {$last}";
            $email = $i === 0 ? 'cliente@demo.sumbanecredit.local' : Str::slug($first.'.'.Str::before($last, ' '), '.').".{$i}@demo.sumbanecredit.local";
            $phone = ['84', '82', '85', '86', '87'][$i % 5].str_pad((string) (1234567 + $i * 98765 % 8765432), 7, '0', STR_PAD_LEFT);

            $user = User::create(['name' => $name, 'email' => $email, 'phone' => $phone, 'password' => $password, 'status' => 'active', 'is_staff' => false]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(User::CLIENT_ROLE);

            $income = [12000, 18500, 25000, 32000, 45000, 60000, 9500, 15000][$i % 8];
            $client = Client::create([
                'user_id' => $user->id,
                'client_number' => app(\App\Services\NumberGenerator::class)->next('client', false),
                'full_name' => $name,
                'document_type' => 'bi',
                'document_number' => '1101'.str_pad((string) (40000000 + $i * 1777), 8, '0', STR_PAD_LEFT).chr(65 + $i % 26),
                'document_expiry' => $this->today->addYears(3)->toDateString(),
                'nuit' => (string) (400100000 + $i * 37),
                'birth_date' => $this->today->subYears(24 + ($i * 3) % 30)->subDays($i * 11)->toDateString(),
                'nationality' => 'Moçambicana',
                'phone' => $phone,
                'email' => $email,
                'occupation' => ['Comerciante', 'Professora', 'Enfermeiro', 'Motorista', 'Costureira', 'Electricista', 'Agricultor', 'Técnico administrativo'][$i % 8],
                'monthly_income' => $income,
                'emergency_contact_name' => ClientFactory::FIRST_NAMES[($i + 5) % 24].' '.ClientFactory::LAST_NAMES[($i + 2) % 20],
                'emergency_contact_phone' => '84'.str_pad((string) (7654321 - $i * 1234), 7, '0', STR_PAD_LEFT),
                'emergency_contact_relationship' => ['Cônjuge', 'Irmão/Irmã', 'Pai/Mãe', 'Filho/Filha'][$i % 4],
                'status' => 'active',
                'kyc_status' => $i < 24 ? 'verified' : 'pending',
                'kyc_verified_by' => $i < 24 ? $this->staff['agente-credito']->id : null,
                'kyc_verified_at' => $i < 24 ? now() : null,
                'is_demo' => true,
                'created_by' => $user->id,
            ]);
            $client->addresses()->create([
                'type' => 'residential', 'province' => $provinces[$i % count($provinces)], 'district' => 'Distrito (demo)',
                'locality' => 'Localidade (demo)', 'neighborhood' => 'Bairro '.($i + 1), 'address_line' => 'Rua '.($i + 3).', casa '.($i * 4 + 1), 'is_primary' => true,
            ]);
            $client->employment()->create([
                'employment_type' => ['self_employed', 'employed', 'employed', 'self_employed', 'informal'][$i % 5],
                'employer_name' => $i % 5 === 1 || $i % 5 === 2 ? 'Empresa Exemplo '.($i + 1).', Lda (demo)' : 'Negócio próprio',
                'position' => $client->occupation, 'monthly_income' => $income, 'other_income' => $i % 3 === 0 ? 3000 : 0,
                'monthly_expenses' => (int) round($income * 0.35), 'start_date' => $this->today->subYears(2 + $i % 6)->toDateString(),
            ]);

            foreach (['bi' => 'bi.pdf', 'proof_of_income' => 'rendimentos.pdf', 'proof_of_address' => 'residencia.pdf'] as $type => $file) {
                $doc = $storage->storeClientDocument($client, $this->samplePdf($file), $type);
                if ($i < 24) {
                    $doc->update(['status' => 'verified', 'verified_by' => $this->staff['agente-credito']->id, 'verified_at' => now()]);
                }
            }
            $clients[] = $client->fresh(['employment', 'user']);
        }

        return $clients;
    }

    // ------------------------------------------------------------------ cenários

    private function runScenarios(array $c): void
    {
        $t = $this->today;

        // Cliente principal (cliente@demo): empréstimo activo em dia + comprovativo pendente
        $loan = $this->originate($c[0], 'PN', '30000', 6, $t->subMonths(3)->subDays(5));
        $this->pay($loan, 3);
        $this->clientProof($loan, $c[0]);

        // Empréstimos liquidados (histórico)
        foreach ([[1, 'CS', '20000', 6, 12], [2, 'PN', '15000', 6, 11], [3, 'CS', '45000', 8, 13], [4, 'PN', '10000', 4, 9]] as [$i, $p, $amt, $n, $months]) {
            $loan = $this->originate($c[$i], $p, $amt, $n, $t->subMonths($months));
            $this->pay($loan, $n, $i === 2 ? 4 : 0);
        }
        // Cliente 1 volta a pedir crédito após liquidar (histórico interno favorável)
        $loan = $this->originate($c[1], 'CS', '35000', 10, $t->subMonths(2)->subDays(10));
        $this->pay($loan, 2);

        // Liquidação antecipada
        $loan = $this->originate($c[5], 'PN', '50000', 12, $t->subMonths(5));
        $this->pay($loan, 2);
        $this->settle($loan, $t->subMonths(5)->addMonths(2)->addDays(12));

        // Activos em dia (inclui semanal e agrícola)
        $loan = $this->originate($c[6], 'CS', '60000', 12, $t->subMonths(4)->subDays(2));
        $this->pay($loan, 4);
        $loan = $this->originate($c[7], 'PN', '25000', 9, $t->subMonths(1)->subDays(3));
        $this->pay($loan, 1);
        $loan = $this->originate($c[8], 'MS', '8000', 12, $t->subWeeks(7));
        $this->pay($loan, 7, 1);
        $loan = $this->originate($c[9], 'CA', '80000', 8, $t->subMonths(3)->subDays(8));
        $this->pay($loan, 3);
        $loan = $this->originate($c[10], 'CS', '18000', 6, $t->subMonths(2)->subDays(15), method: 'equal_principal');
        $this->pay($loan, 2);
        $loan = $this->originate($c[11], 'PN', '40000', 12, $t->subDays(20), frequency: 'biweekly');
        $this->pay($loan, 1);

        // Em atraso (cobranças)
        foreach ([[12, 'PN', '20000', 6, 5, 1], [13, 'CS', '30000', 8, 6, 2], [14, 'MS', '6000', 16, 2, 4], [15, 'PN', '12000', 6, 4, 0]] as [$i, $p, $amt, $n, $months, $paid]) {
            $start = $p === 'MS' ? $t->subWeeks(9) : $t->subMonths($months);
            $loan = $this->originate($c[$i], $p, $amt, $n, $start);
            $this->pay($loan, $paid);
        }

        // Pagamento parcial
        $loan = $this->originate($c[16], 'CS', '24000', 6, $t->subMonths(2)->subDays(10));
        $this->pay($loan, 1);
        $next = $loan->fresh()->nextInstallment();
        if ($next && $next->due_date->lte($t)) {
            $this->travel(CarbonImmutable::parse($next->due_date)->addDays(2));
            $this->registerAndConfirm($loan, Money::toDb(Money::div($next->outstandingTotal(), '2')), 'cash', null);
        }

        // Reestruturação (após atraso)
        $loan = $this->originate($c[17], 'PN', '35000', 6, $t->subMonths(5));
        $this->pay($loan, 1);
        $this->travel($t->subMonths(1));
        $this->actingAs($this->staff['gestor-credito']);
        app(RestructuringService::class)->restructure($loan->fresh(), $this->staff['gestor-credito'], [
            'term' => 8, 'interest_rate' => '3.5', 'amortization_method' => 'annuity', 'repayment_frequency' => 'monthly',
            'start_date' => $t->subMonths(1)->toDateString(), 'capitalize_overdue' => true,
            'reason' => 'Redução temporária de rendimentos comprovada; plano ajustado à nova capacidade de pagamento (demonstração).',
        ]);
        $this->pay($loan->fresh(), 1);

        // Estorno de pagamento (referência errada)
        $loan = $this->originate($c[18], 'CS', '15000', 6, $t->subMonths(2)->subDays(3));
        $this->pay($loan, 2);
        $last = Payment::where('loan_id', $loan->id)->where('status', 'confirmed')->latest('confirmed_at')->first();
        $this->travel($t->subDays(5));
        $this->actingAs($this->staff['contabilista']);
        app(PaymentService::class)->reverse($last, $this->staff['contabilista'], 'Referência M-Pesa não consta do extracto do prestador — pagamento registado por engano (demonstração).');

        // Fases intermédias do processo
        $this->originate($c[19], 'PN', '22000', 6, $t->subDays(1), stop: 'approved');
        $this->originate($c[20], 'CS', '28000', 10, $t->subDays(1), stop: 'disb_pending');
        $this->originate($c[21], 'CA', '60000', 6, $t->subDays(1), stop: 'disb_authorized');
        $this->originate($c[22], 'CS', '150000', 12, $t->subDays(2), stop: 'forwarded');
        $this->originate($c[23], 'PN', '18000', 6, $t->subDays(3), stop: 'pending_documents');
        $this->originate($c[24], 'PN', '140000', 18, $t->subDays(4), stop: 'rejected');
        $this->originate($c[25], 'CS', '12000', 6, $t->subDays(6), stop: 'cancelled');
        $this->originate($c[25], 'PN', '9000', 4, $t, stop: 'submitted');
        $this->originate($c[2], 'MS', '5000', 8, $t->subDays(1), stop: 'review');
    }

    /** Percorre o fluxo completo até ao ponto indicado. */
    private function originate(Client $client, string $code, string $amount, int $term, CarbonImmutable $date, string $stop = 'disbursed', ?string $method = null, ?string $frequency = null): Loan|LoanApplication|null
    {
        $product = $this->products[$code];
        $v = $product->currentVersion;
        $agent = $client->id % 2 ? $this->staff['agente-credito'] : $this->staff['agente-credito-2'];
        $gestor = $this->staff['gestor-credito'];
        $emp = $client->employment;

        $this->travel($date->subDays(6));
        $this->actingAs($client->user);
        $application = app(LoanApplicationService::class)->submit($client->fresh(), [
            'loan_product_id' => $product->id, 'amount' => $amount, 'term' => $term,
            'frequency' => $frequency ?? $v->allowed_frequencies[0], 'method' => $method ?? $v->amortization_methods[0],
            'purpose' => ['Compra de mercadoria para revenda', 'Aquisição de equipamento de trabalho', 'Pagamento de propinas', 'Reabilitação da casa', 'Insumos para a campanha agrícola'][$client->id % 5].' (demonstração)',
            'monthly_income' => (string) $emp->monthly_income, 'other_income' => (string) $emp->other_income,
            'monthly_expenses' => (string) $emp->monthly_expenses, 'existing_obligations' => $client->id % 4 === 0 ? '1500' : '0',
            'employment_type' => $emp->employment_type, 'employer_name' => $emp->employer_name, 'occupation' => $client->occupation,
            'guarantors' => $v->eligibility('requires_guarantor') ? [['full_name' => 'Fiador '.ClientFactory::FIRST_NAMES[$client->id % 24].' (demo)', 'document_type' => 'bi', 'document_number' => '1102'.random_int(10000000, 99999999).'B', 'phone' => '84'.random_int(1000000, 9999999), 'relationship' => 'Vizinho(a)', 'monthly_income' => '15000']] : [],
            'collaterals' => $v->eligibility('requires_collateral') ? [['type' => 'equipment', 'description' => 'Motobomba de irrigação (demonstração)', 'estimated_value' => '95000']] : [],
            'document_ids' => $client->documents()->pluck('id')->all(),
            'declaration' => true, 'consent_data_processing' => true, 'consent_credit_check' => true,
        ], $client->user);
        if ($stop === 'submitted') {
            return $application;
        }
        if ($stop === 'cancelled') {
            $this->travel($date->subDays(5));
            app(LoanApplicationService::class)->cancel($application, $client->user, 'Já não necessito do crédito (demonstração).');

            return $application;
        }

        $apps = app(LoanApplicationService::class);
        $this->travel($date->subDays(5));
        $this->actingAs($agent);
        $apps->startReview($application, $agent);
        app(CreditAssessmentService::class)->assess($application->fresh(), $agent);
        if ($stop === 'review') {
            return $application;
        }
        if ($stop === 'pending_documents') {
            $apps->requestInformation($application, $agent, 'Envie o extracto bancário ou registo de vendas dos últimos 3 meses (demonstração).');

            return $application;
        }
        $apps->addNote($application->fresh(), $agent, 'Documentos conferidos com os originais. Actividade confirmada por visita (demonstração).');
        $apps->forward($application->fresh(), $agent, 'Parecer favorável do analista.');
        if ($stop === 'forwarded') {
            return $application;
        }

        $this->travel($date->subDays(3));
        $this->actingAs($gestor);
        if ($stop === 'rejected') {
            $apps->reject($application->fresh(), $gestor, 'A prestação proposta excede a capacidade de pagamento demonstrada. Pode voltar a pedir um montante inferior (demonstração).');

            return $application;
        }
        $loan = $apps->approve($application->fresh(), $gestor, $amount, $term, 'Aprovado de acordo com a análise (demonstração).');
        if ($stop === 'approved') {
            return $loan;
        }

        $this->travel($date->subDays(2));
        $this->actingAs($agent);
        app(LoanService::class)->formalize($loan, $agent, $date->subDays(2)->toDateString(), null, 'Contrato assinado no balcão (demonstração).');
        $disbursement = app(DisbursementService::class)->request($loan->fresh(), $agent, [
            'method' => $client->id % 3 === 0 ? 'bank_transfer' : 'mpesa', 'destination_account' => $client->phone, 'notes' => null,
        ], (string) Str::uuid());
        if ($stop === 'disb_pending') {
            return $loan;
        }

        $this->travel($date->subDays(1));
        $this->actingAs($gestor);
        app(DisbursementService::class)->authorize($disbursement, $gestor);
        if ($stop === 'disb_authorized') {
            return $loan;
        }

        $this->travel($date);
        $this->actingAs($this->staff['caixa']);
        app(DisbursementService::class)->complete($disbursement->fresh(), $this->staff['caixa'], [
            'reference' => $disbursement->method === PaymentMethod::Cash ? null : $this->ref('DSB'),
            'disbursed_at' => $date->toDateString(),
        ]);

        return $loan->fresh();
    }

    /** Paga as próximas $count prestações nas datas de vencimento (+ $lateDays). */
    private function pay(Loan $loan, int $count, int $lateDays = 0): void
    {
        for ($k = 0; $k < $count; $k++) {
            $loan = $loan->fresh();
            if (! $loan->isActive()) {
                return;
            }
            $next = LoanInstallment::where('loan_id', $loan->id)->where('schedule_version', $loan->schedule_version)
                ->whereIn('status', InstallmentStatus::openValues())->orderBy('due_date')->first();
            if (! $next) {
                return;
            }
            $date = CarbonImmutable::parse($next->due_date)->addDays($lateDays + ($k % 3 === 2 ? 1 : 0))->subDays($k % 2);
            if ($date->gt($this->today)) {
                return;
            }
            $this->travel($date);
            $method = ['mpesa', 'emola', 'cash', 'bank_deposit'][($loan->id + $k) % 4];
            $this->registerAndConfirm($loan, $next->outstandingTotal(), $method, $method === 'cash' ? null : $this->ref());
        }
    }

    private function registerAndConfirm(Loan $loan, string $amount, string $method, ?string $reference): Payment
    {
        $this->actingAs($this->staff['caixa']);
        $service = app(PaymentService::class);
        $payment = $service->register($loan->fresh(), $this->staff['caixa'], [
            'amount' => $amount, 'method' => $method, 'reference' => $reference, 'paid_at' => CarbonImmutable::today()->toDateString(),
        ], 'staff', null, (string) Str::uuid());

        return $service->confirm($payment, $this->staff['caixa']);
    }

    private function settle(Loan $loan, CarbonImmutable $date): void
    {
        if ($date->gt($this->today)) {
            return;
        }
        $this->travel($date);
        $this->actingAs($this->staff['caixa']);
        $payment = app(SettlementService::class)->register($loan->fresh(), $this->staff['caixa'], [
            'method' => 'bank_transfer', 'reference' => $this->ref('TRF'), 'paid_at' => $date->toDateString(),
        ], 'staff', null, (string) Str::uuid());
        app(PaymentService::class)->confirm($payment, $this->staff['caixa']);
    }

    private function clientProof(Loan $loan, Client $client): void
    {
        $next = $loan->fresh()->nextInstallment();
        if (! $next) {
            return;
        }
        $this->travel($this->today->subDays(1));
        $this->actingAs($client->user);
        app(PaymentService::class)->register($loan->fresh(), $client->user, [
            'amount' => $next->outstandingTotal(), 'method' => 'mpesa', 'reference' => $this->ref('MP'),
            'paid_at' => $this->today->subDays(1)->toDateString(), 'payer_phone' => $client->phone, 'notes' => 'Pagamento antecipado da prestação (demonstração).',
        ], 'client_portal', $this->samplePdf('comprovativo-mpesa.pdf'), (string) Str::uuid());
    }

    private function createSupportAndMessages(Client $client): void
    {
        $support = app(SupportService::class);
        $this->travel($this->today->subDays(3));
        $this->actingAs($client->user);
        $ticket = $support->open($client, $client->user, [
            'subject' => 'Dúvida sobre a data da próxima prestação', 'category' => 'payments', 'priority' => 'normal',
            'message' => 'Bom dia. Posso pagar a próxima prestação alguns dias antes do vencimento? (demonstração)',
        ], null);
        $this->travel($this->today->subDays(2));
        $this->actingAs($this->staff['agente-credito']);
        $support->reply($ticket, $this->staff['agente-credito'], 'Bom dia. Sim, pode pagar antecipadamente; o valor é imputado à prestação seguinte. Envie o comprovativo pelo portal. (demonstração)', null);
        $this->travel(null);

        foreach ([
            ['Paulo Sitoe (demo)', 'paulo.sitoe@demo.sumbanecredit.local', 'Informação sobre crédito para negócio', 'Gostaria de saber que documentos preciso para o Crédito Pequeno Negócio. (demonstração)', 'new'],
            ['Teresa Langa (demo)', 'teresa.langa@demo.sumbanecredit.local', 'Horário de atendimento', 'Qual é o horário de atendimento ao sábado? (demonstração)', 'answered'],
        ] as [$name, $email, $subject, $message, $status]) {
            ContactMessage::create(['name' => $name, 'email' => $email, 'phone' => '84'.random_int(1000000, 9999999), 'subject' => $subject,
                'message' => $message, 'status' => $status, 'ip_address' => '127.0.0.1', 'handled_by' => $status === 'answered' ? $this->staff['admin']->id : null]);
        }
    }

    private function createCollectionActivity(): void
    {
        $service = app(CollectionService::class);
        $cases = CollectionCase::open()->with('loan')->get();
        foreach ($cases as $idx => $case) {
            $agent = $this->staff[$idx % 2 ? 'agente-credito' : 'agente-credito-2'];
            $this->actingAs($agent);
            $case->update(['assigned_to' => $agent->id]);
            $service->logContact($case, $agent, [
                'channel' => 'call', 'outcome' => 'no_answer', 'notes' => 'Telefone sem resposta (demonstração).', 'contacted_at' => now()->subDays(3),
            ]);
            $service->logContact($case->fresh(), $agent, [
                'channel' => 'sms', 'outcome' => 'contacted', 'notes' => 'Enviada mensagem a lembrar o valor em atraso (demonstração).', 'contacted_at' => now()->subDays(2),
                'next_action_at' => now()->addDay(),
            ]);
            if ($idx === 0) {
                $service->logContact($case->fresh(), $agent, [
                    'channel' => 'visit', 'outcome' => 'promise', 'notes' => 'Cliente comprometeu-se a regularizar (demonstração).', 'contacted_at' => now()->subDay(),
                    'promised_amount' => Money::toDb($case->overdue_amount), 'promised_date' => $this->today->addDays(5)->toDateString(),
                ]);
            }
        }
    }

    private function printCredentials(string $password): void
    {
        if (! $this->command) {
            return;
        }
        $this->command->newLine();
        $this->command->info('Dados de demonstração criados (APENAS para ambiente local).');
        $this->command->table(['Perfil', 'Email', 'Palavra-passe'], [
            ['Super Administrador', 'superadmin@demo.sumbanecredit.local', $password],
            ['Administrador', 'admin@demo.sumbanecredit.local', $password],
            ['Gestor de Crédito', 'gestor@demo.sumbanecredit.local', $password],
            ['Gestor de Crédito (2.º)', 'gestor2@demo.sumbanecredit.local', $password],
            ['Agente de Crédito', 'agente@demo.sumbanecredit.local', $password],
            ['Caixa', 'caixa@demo.sumbanecredit.local', $password],
            ['Contabilista', 'contabilista@demo.sumbanecredit.local', $password],
            ['Cliente', 'cliente@demo.sumbanecredit.local', $password],
        ]);
        $this->command->line(sprintf('Clientes: %d · Pedidos: %d · Empréstimos: %d · Pagamentos: %d · Desembolsos: %d',
            Client::count(), LoanApplication::count(), Loan::count(), Payment::count(), Disbursement::count()));
    }
}
