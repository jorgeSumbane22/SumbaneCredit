<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Quando activado nas definições, obriga os funcionários a configurar 2FA. */
class RequireTwoFactorForStaff
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->isStaff() && ! $user->hasTwoFactorEnabled() && $this->settings->bool('security.require_2fa_staff')) {
            return redirect()->route('account.security')->with('warning', 'A autenticação de dois factores é obrigatória para funcionários. Configure-a para continuar.');
        }

        return $next($request);
    }
}
