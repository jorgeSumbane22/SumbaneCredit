<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        // Resposta idêntica exista ou não a conta (não revela emails registados).
        return back()->with('success', 'Se o email estiver registado, receberá uma ligação para redefinir a palavra-passe.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => (string) $request->input('password'),
                    'remember_token' => Str::random(60),
                    'password_changed_at' => now(),
                ])->save();
                if (! $user->email_verified_at) {
                    $user->forceFill(['email_verified_at' => now()])->save(); // a ligação foi recebida no email
                }
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $this->audit->log('auth.password_reset', $user, null, null, 'Palavra-passe redefinida', 'success', $user->id);
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Palavra-passe redefinida. Já pode iniciar sessão.')
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
