<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\Auth\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Segurança da conta: palavra-passe, sessões activas e autenticação de dois factores. */
class SecurityController extends Controller
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly AuditService $audit,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $sessions = DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()
            ->map(fn ($s) => (object) [
                'id' => $s->id,
                'ip' => $s->ip_address,
                'agent' => mb_strimwidth((string) $s->user_agent, 0, 90, '…'),
                'last_activity' => \Carbon\Carbon::createFromTimestamp($s->last_activity),
                'current' => $s->id === $request->session()->getId(),
            ]);

        $pendingSecret = $request->session()->get('2fa.pending_secret');

        return view('account.security', [
            'user' => $user,
            'sessions' => $sessions,
            'pendingSecret' => $pendingSecret,
            'otpauthUrl' => $pendingSecret ? $this->totp->otpauthUrl(config('app.name'), $user->email, $pendingSecret) : null,
            'recoveryCodes' => $request->session()->get('2fa.recovery_codes'),
            'layout' => $user->isStaff() ? 'layouts.admin' : 'layouts.client',
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->mixedCase()->numbers()],
        ], ['current_password.current_password' => 'A palavra-passe actual está incorrecta.']);

        $user = $request->user();
        $user->forceFill(['password' => (string) $request->input('password'), 'password_changed_at' => now()])->save();
        // Termina as restantes sessões após mudança de palavra-passe.
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        $this->audit->log('auth.password_changed', $user, null, null, 'Palavra-passe alterada');

        return back()->with('success', 'Palavra-passe alterada. As outras sessões foram terminadas.');
    }

    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Palavra-passe incorrecta.']);
        Auth::logoutOtherDevices((string) $request->input('password'));
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        $this->audit->log('auth.sessions_terminated', $request->user(), null, null, 'Outras sessões terminadas');

        return back()->with('success', 'As restantes sessões foram terminadas.');
    }

    public function startTwoFactor(Request $request): RedirectResponse
    {
        $request->session()->put('2fa.pending_secret', $this->totp->generateSecret());

        return back()->with('info', 'Adicione a chave à sua aplicação autenticadora e confirme com o código gerado.');
    }

    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $secret = $request->session()->get('2fa.pending_secret');
        if (! $secret || ! $this->totp->verify($secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Código inválido. Verifique a hora do telemóvel e tente novamente.']);
        }

        $codes = $this->totp->recoveryCodes();
        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('2fa.pending_secret');
        $request->session()->flash('2fa.recovery_codes', $codes);
        $this->audit->log('auth.2fa_enabled', $user, null, null, 'Autenticação de dois factores activada');

        return back()->with('success', 'Autenticação de dois factores activada. Guarde os códigos de recuperação.');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Palavra-passe incorrecta.']);
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $this->audit->log('auth.2fa_disabled', $request->user(), null, null, 'Autenticação de dois factores desactivada');

        return back()->with('success', 'Autenticação de dois factores desactivada.');
    }
}
