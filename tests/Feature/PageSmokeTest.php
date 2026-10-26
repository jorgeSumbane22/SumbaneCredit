<?php

namespace Tests\Feature;

use App\Models\CollectionCase;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Loans\LoanMonitoringService;
use App\Services\Reports\ReportService;
use App\Services\SupportService;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Percorre todas as páginas (público, portal, painel, PDF e Excel) com dados reais,
 * garantindo que nenhuma view, rota ou consulta falha.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $this->seedPermissions();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $product = $this->product(['fees' => [['name' => 'Comissão', 'type' => 'percent', 'value' => 2, 'charge' => 'upfront']]]);
        $client = $this->clientUser();
        $loan = $this->activeLoan($client, $product, '2026-01-15', ['amount' => '9000', 'term' => 3]);
        $payment = $this->pay($loan, '1000', '2026-02-01');
        $this->atDate('2026-03-01');
        app(LoanMonitoringService::class)->processOverdue(CarbonImmutable::parse('2026-03-01'));
        $other = $this->clientUser();
        $application = $this->submitApplication($other, $product);
        Auth::guard('web')->setUser($client);
        $ticket = app(SupportService::class)->open($client->client, $client, ['subject' => 'Ajuda', 'category' => 'payments', 'message' => 'Preciso de ajuda com o pagamento.'], null);
        Auth::guard('web')->logout();

        return compact('product', 'client', 'loan', 'payment', 'application', 'ticket');
    }

    private function superUser(): User
    {
        // Utilizador de teste com todas as permissões (apenas para percorrer as páginas).
        $role = Role::findOrCreate('qa-all', 'web');
        $role->syncPermissions(array_keys(Permissions::ALL));
        $user = User::factory()->create(['is_staff' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_public_pages_render(): void
    {
        $s = $this->scenario();
        foreach (['/', '/sobre-nos', '/produtos', '/produtos/'.$s['product']->slug, '/simulador', '/como-funciona', '/perguntas-frequentes',
            '/contactos', '/privacidade', '/termos-e-condicoes', '/entrar', '/registar', '/recuperar-palavra-passe'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/pagina-inexistente')->assertNotFound()->assertSee('Página não encontrada');
    }

    public function test_client_portal_pages_render(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['client']);
        $urls = [
            route('client.dashboard'), route('client.profile.edit'), route('client.documents.index'),
            route('client.applications.index'), route('client.applications.create'), route('client.applications.show', $s['loan']->application),
            route('client.loans.index'), route('client.loans.show', $s['loan']), route('client.installments.index'),
            route('client.installments.index', ['filtro' => 'em-atraso']), route('client.payments.index'), route('client.payments.create'),
            route('client.payments.show', $s['payment']), route('client.tickets.index'), route('client.tickets.create'),
            route('client.tickets.show', $s['ticket']), route('notifications.index'), route('account.security'),
        ];
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
        foreach ([route('client.loans.contract', $s['loan']), route('client.loans.schedule', $s['loan']), route('client.loans.statement', $s['loan']), route('client.payments.receipt', $s['payment'])] as $pdf) {
            $this->get($pdf)->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_admin_pages_render(): void
    {
        $s = $this->scenario();
        $this->actingAs($this->superUser());
        $collection = CollectionCase::firstOrFail();
        $disbursement = $s['loan']->disbursements()->first();

        $urls = [
            route('admin.dashboard'), route('admin.dashboard', ['periodo' => 'this_year']), route('admin.dashboard', ['periodo' => 'custom', 'de' => '2026-01-01', 'ate' => '2026-03-31']),
            route('admin.clients.index'), route('admin.clients.index', ['q' => 'a', 'situacao' => 'em-atraso']), route('admin.clients.create'),
            route('admin.clients.show', $s['client']->client), route('admin.clients.edit', $s['client']->client),
            route('admin.clients.applications.create', $s['client']->client),
            route('admin.staff.index'), route('admin.staff.create'), route('admin.staff.show', $this->staff('caixa')), route('admin.staff.edit', $this->staff('caixa')),
            route('admin.roles.index'),
            route('admin.products.index'), route('admin.products.create'), route('admin.products.show', $s['product']), route('admin.products.edit', $s['product']),
            route('admin.applications.index'), route('admin.applications.index', ['estado' => 'abertos']), route('admin.applications.show', $s['application']),
            route('admin.applications.show', $s['loan']->application),
            route('admin.loans.index'), route('admin.loans.index', ['atraso' => 'sim']), route('admin.loans.show', $s['loan']), route('admin.loans.settlement', $s['loan']),
            route('admin.disbursements.index'), route('admin.disbursements.show', $disbursement),
            route('admin.payments.index'), route('admin.payments.create'), route('admin.payments.create', ['q' => $s['loan']->loan_number]),
            route('admin.payments.create', ['emprestimo' => $s['loan']->id]), route('admin.payments.show', $s['payment']), route('admin.reconciliation.index'),
            route('admin.collections.index'), route('admin.collections.show', $collection),
            route('admin.reports.index'), route('admin.audit.index'), route('admin.audit.show', \App\Models\AuditLog::first()),
            route('admin.settings.index'), route('admin.faqs.index'), route('admin.testimonials.index'), route('admin.assessment-rules.index'),
            route('admin.tickets.index'), route('admin.tickets.show', $s['ticket']), route('admin.messages.index'),
            route('notifications.index'), route('account.security'),
        ];
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }

        foreach (array_keys(ReportService::TYPES) as $type) {
            $this->get(route('admin.reports.show', ['type' => $type, 'periodo' => 'this_year']))->assertOk();
            $this->get(route('admin.reports.pdf', ['type' => $type, 'periodo' => 'this_year']))->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->get(route('admin.reports.excel', ['type' => $type, 'periodo' => 'this_year']))->assertOk()->assertDownload();
        }
        $this->get(route('admin.clients.export'))->assertOk()->assertDownload();
        foreach ([route('admin.loans.contract', $s['loan']), route('admin.loans.schedule', $s['loan']), route('admin.loans.statement', $s['loan']),
            route('admin.payments.receipt', $s['payment']), route('admin.disbursements.pdf', $disbursement)] as $pdf) {
            $this->get($pdf)->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_each_staff_role_sees_dashboard(): void
    {
        $this->scenario();
        foreach (array_keys(User::STAFF_ROLES) as $role) {
            $this->actingAs($this->staff($role))->get(route('admin.dashboard'))->assertOk();
        }
    }
}
