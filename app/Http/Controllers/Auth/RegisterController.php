<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Services\AuditService;
use App\Services\ClientService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(
        private readonly ClientService $clients,
        private readonly AuditService $audit,
    ) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => 'active',
                'is_staff' => false,
            ]);
            $user->assignRole(User::CLIENT_ROLE);
            $this->clients->registerFromPortal($user, $data + ['nationality' => null]);
            $this->audit->log('auth.registered', $user, null, ['email' => $user->email], 'Registo de cliente', 'success', $user->id);

            return $user;
        });

        event(new Registered($user)); // envia email de verificação
        $user->notify(new WelcomeNotification);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')->with('success', 'Conta criada. Confirme o seu email para aceder ao portal.');
    }
}
