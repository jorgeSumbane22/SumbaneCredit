<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/cliente')->assertRedirect(route('login'));
    }

    public function test_clients_cannot_access_admin_area(): void
    {
        $this->actingAs($this->clientUser())->get('/admin')->assertForbidden();
        $this->get('/admin/clientes')->assertForbidden();
    }

    public function test_staff_are_redirected_out_of_client_portal(): void
    {
        $this->actingAs($this->staff('caixa'))->get('/cliente')->assertRedirect(route('admin.dashboard'));
    }

    public function test_routes_enforce_permissions_in_backend(): void
    {
        $product = $this->product();
        $application = $this->submitApplication($this->clientUser(), $product);

        // Agente: analisa mas não aprova
        $this->actingAs($this->staff('agente-credito'))
            ->post(route('admin.applications.approve', $application), ['approved_amount' => 10000, 'approved_term' => 3])
            ->assertForbidden();
        // Caixa não gere funcionários nem produtos
        $this->actingAs($this->staff('caixa'))->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($this->staff('caixa'))->get(route('admin.products.create'))->assertForbidden();
        // Contabilista não regista desembolsos
        $loan = $this->approvedLoan($this->clientUser(), $product);
        $this->actingAs($this->staff('contabilista'))->post(route('admin.loans.disbursements.store', $loan), ['method' => 'cash'])->assertForbidden();
        // Agente não estorna pagamentos
        $this->actingAs($this->staff('agente-credito'))->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_super_admin_cannot_approve_disburse_or_reverse(): void
    {
        $super = $this->staff('super-admin');
        foreach (Permissions::SUPER_ADMIN_FORBIDDEN as $permission) {
            $this->assertFalse($super->can($permission), "super-admin não deve ter {$permission}");
        }

        $product = $this->product();
        $application = $this->submitApplication($this->clientUser(), $product);
        $this->actingAs($super)->post(route('admin.applications.approve', $application), ['approved_amount' => 10000, 'approved_term' => 3])->assertForbidden();

        // Mas gere funcionários e permissões
        $this->actingAs($super)->get(route('admin.staff.index'))->assertOk();
        $this->actingAs($super)->get(route('admin.roles.index'))->assertOk();
    }

    public function test_super_admin_role_cannot_receive_forbidden_permissions(): void
    {
        $super = $this->staff('super-admin');
        $role = Role::findByName('super-admin');

        $this->actingAs($super)->put(route('admin.roles.update', $role), [
            'permissions' => array_merge(Permissions::ROLES['super-admin'], ['applications.approve']),
        ])->assertSessionHas('error');

        $this->assertFalse($role->fresh()->hasPermissionTo('applications.approve'));
    }

    public function test_admin_cannot_create_super_admin(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'Intruso', 'email' => 'intruso@example.test', 'role' => 'super-admin',
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.test']);
    }

    public function test_staff_creation_and_deactivation(): void
    {
        $super = $this->staff('super-admin');
        $this->actingAs($super)->post(route('admin.staff.store'), [
            'name' => 'Novo Caixa', 'email' => 'novo.caixa@example.test', 'role' => 'caixa',
        ])->assertRedirect();
        $user = User::where('email', 'novo.caixa@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('caixa'));
        $this->assertTrue($user->is_staff);

        $this->actingAs($super)->post(route('admin.staff.toggle', $user))->assertSessionHas('success');
        $this->assertSame('inactive', $user->fresh()->status);

        // Não pode desactivar-se a si próprio
        $this->actingAs($super)->post(route('admin.staff.toggle', $super))->assertSessionHas('error');
    }
}
