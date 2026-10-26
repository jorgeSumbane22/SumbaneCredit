<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Auth\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly AuditService $audit,
        private readonly LoginController $login,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['nullable', 'string'], 'recovery_code' => ['nullable', 'string']]);
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->with('error', 'A sessão de autenticação expirou. Inicie sessão novamente.');
        }

        $key = '2fa:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Demasiadas tentativas. Aguarde alguns minutos.']);
        }

        $valid = false;
        if ($request->filled('code')) {
            $valid = $this->totp->verify($user->two_factor_secret, (string) $request->input('code'));
        } elseif ($request->filled('recovery_code')) {
            $codes = $user->two_factor_recovery_codes ?? [];
            $input = strtoupper(trim((string) $request->input('recovery_code')));
            if (in_array($input, $codes, true)) {
                $user->two_factor_recovery_codes = array_values(array_diff($codes, [$input]));
                $user->save();
                $valid = true;
            }
        }

        if (! $valid) {
            RateLimiter::hit($key, 300);
            $this->audit->log('auth.2fa_failed', $user, null, null, 'Código de dois factores inválido', 'failure', $user->id);
            throw ValidationException::withMessages(['code' => 'O código indicado não é válido.']);
        }

        RateLimiter::clear($key);
        $remember = (bool) ($request->session()->get('login.2fa.remember'));
        $request->session()->forget('login.2fa');

        return $this->login->completeLogin($request, $user, $remember);
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('login.2fa');
        if (! $pending || now()->timestamp - ($pending['at'] ?? 0) > 600) {
            return null;
        }

        return User::find($pending['id']);
    }
}
