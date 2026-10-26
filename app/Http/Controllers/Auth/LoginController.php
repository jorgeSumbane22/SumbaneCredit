<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $user = User::where('email', (string) $request->input('email'))->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            $request->hitRateLimiter();
            $this->audit->log('auth.login_failed', $user, null, null, 'Tentativa de autenticação falhada para '.(string) $request->input('email'), 'failure');
            throw ValidationException::withMessages(['email' => 'As credenciais indicadas não são válidas.']);
        }

        if (! $user->isActive()) {
            $this->audit->log('auth.login_blocked', $user, null, null, 'Conta inactiva', 'failure', $user->id);
            throw ValidationException::withMessages(['email' => 'A sua conta está desactivada. Contacte a instituição.']);
        }

        $request->clearRateLimiter();

        // Segundo factor: a sessão só é iniciada após validação do código.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('login.2fa', ['id' => $user->id, 'remember' => $request->boolean('remember'), 'at' => now()->timestamp]);

            return redirect()->route('two-factor.challenge');
        }

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    public function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        $this->audit->log('auth.login', $user, null, null, 'Início de sessão', 'success', $user->id);

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->audit->log('auth.logout', $request->user(), null, null, 'Fim de sessão');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('public.home')->with('success', 'Sessão terminada com segurança.');
    }
}
