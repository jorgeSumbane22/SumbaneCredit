<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Portal do cliente: apenas utilizadores com perfil de cliente e ficha associada. */
class EnsureClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->isStaff()) {
            return redirect()->route('admin.dashboard');
        }
        if (! $user || ! $user->isClient() || ! $user->client) {
            abort(403, 'Acesso reservado a clientes.');
        }

        return $next($request);
    }
}
