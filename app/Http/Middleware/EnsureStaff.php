<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Área administrativa: apenas funcionários com acesso ao painel. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isStaff() || ! $user->can('dashboard.admin')) {
            abort(403, 'Acesso reservado a funcionários autorizados.');
        }

        return $next($request);
    }
}
