<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequireTwoFactorForStaff;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'staff' => EnsureStaff::class,
            'client' => EnsureClient::class,
            'staff.2fa' => RequireTwoFactorForStaff::class,
        ]);
        $middleware->web(append: [
            EnsureUserIsActive::class,
            SecurityHeaders::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Regras de negócio violadas: mensagem ao utilizador (a transacção já foi revertida).
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });

        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            abort(403, 'Não tem permissão para realizar esta operação.');
        });

        $exceptions->dontReport([BusinessRuleException::class]);
    })->create();
