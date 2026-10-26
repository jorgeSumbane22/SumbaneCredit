<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/entrar')->assertOk()->assertSee('Bem-vindo(a) de volta');
    }

    public function test_staff_login_redirects_to_admin_dashboard(): void
    {
        $user = $this->staff('gestor-credito');

        $this->post('/entrar', ['email' => $user->email, 'password' => 'Password1'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('home'))->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_client_login_redirects_to_portal(): void
    {
        $user = $this->clientUser();

        $this->post('/entrar', ['email' => $user->email, 'password' => 'Password1']);
        $this->get(route('home'))->assertRedirect(route('client.dashboard'));
        $this->get(route('client.dashboard'))->assertOk();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = $this->staff('caixa');

        $this->post('/entrar', ['email' => $user->email, 'password' => 'errada'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed', 'result' => 'failure']);
    }

    public function test_excessive_login_attempts_are_throttled(): void
    {
        $user = $this->staff('caixa');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/entrar', ['email' => $user->email, 'password' => 'errada']);
        }

        $response = $this->post('/entrar', ['email' => $user->email, 'password' => 'Password1']);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Demasiadas tentativas', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login_and_is_logged_out(): void
    {
        $user = $this->staff('caixa');
        $user->update(['status' => 'inactive']);

        $this->post('/entrar', ['email' => $user->email, 'password' => 'Password1'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $user->update(['status' => 'active']);
        $this->actingAs($user);
        $user->update(['status' => 'inactive']);
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs($this->staff('caixa'));
        $this->post('/sair')->assertRedirect(route('public.home'));
        $this->assertGuest();
    }

    public function test_client_registration_creates_user_client_and_sends_verification(): void
    {
        $this->seedPermissions();
        Notification::fake();

        $this->post('/registar', [
            'full_name' => 'Celeste Machava Cossa', 'email' => 'celeste@example.test', 'phone' => '84 123 4567',
            'document_type' => 'bi', 'document_number' => '110100000001a', 'birth_date' => '1990-05-10', 'province' => 'Gaza',
            'password' => 'Segura123', 'password_confirmation' => 'Segura123', 'terms' => '1', 'privacy' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'celeste@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('cliente'));
        $this->assertFalse($user->is_staff);
        $client = Client::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('110100000001A', $client->document_number);
        $this->assertSame('841234567', $client->phone);
        $this->assertSame('Gaza', $client->address->province);
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        // Sem email verificado não acede ao portal
        $this->get(route('client.dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_registration_validates_data(): void
    {
        $this->seedPermissions();
        $this->post('/registar', [
            'full_name' => 'X', 'email' => 'invalido', 'phone' => '123', 'document_type' => 'bi', 'document_number' => '',
            'birth_date' => now()->subYears(15)->toDateString(), 'province' => 'Lisboa', 'password' => 'fraca', 'password_confirmation' => 'outra',
        ])->assertSessionHasErrors(['full_name', 'email', 'phone', 'document_number', 'birth_date', 'province', 'password', 'terms', 'privacy']);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_email_verification(): void
    {
        $user = $this->clientUser();
        $user->forceFill(['email_verified_at' => null])->save();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($url)->assertRedirect(route('home'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = $this->clientUser();

        $this->post('/recuperar-palavra-passe', ['email' => $user->email])->assertSessionHas('success');
        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post('/redefinir-palavra-passe', [
            'token' => $token, 'email' => $user->email, 'password' => 'NovaSenha123', 'password_confirmation' => 'NovaSenha123',
        ])->assertRedirect(route('login'));
        $this->post('/entrar', ['email' => $user->email, 'password' => 'NovaSenha123']);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_password_reset_does_not_reveal_unknown_emails(): void
    {
        $this->post('/recuperar-palavra-passe', ['email' => 'ninguem@example.test'])->assertSessionHas('success');
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = $this->staff('caixa');
        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'errada', 'password' => 'Nova12345', 'password_confirmation' => 'Nova12345',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'Password1', 'password' => 'Nova12345', 'password_confirmation' => 'Nova12345',
        ])->assertSessionHas('success');
    }

    public function test_two_factor_authentication_flow(): void
    {
        $user = $this->staff('gestor-credito');
        $totp = app(TotpService::class);

        $this->actingAs($user)->post(route('account.2fa.start'));
        $secret = session('2fa.pending_secret');
        $this->assertNotEmpty($secret);
        $this->post(route('account.2fa.confirm'), ['code' => $totp->code($secret)])->assertSessionHas('success');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->post('/sair');
        $this->post('/entrar', ['email' => $user->email, 'password' => 'Password1'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->post('/dois-factores', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/dois-factores', ['code' => $totp->code($secret)]);
        $this->assertAuthenticatedAs($user->fresh());
    }
}
