<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditService;
use App\Support\PermissionSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Criação segura do primeiro Super Administrador (produção).
 * A palavra-passe é pedida de forma oculta e nunca é escrita em ficheiros ou no histórico.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'sumbanecredit:create-admin {--name= : Nome completo} {--email= : Email}';

    protected $description = 'Cria um utilizador Super Administrador (gestão técnica, utilizadores e permissões)';

    public function handle(AuditService $audit): int
    {
        PermissionSync::run();

        $name = $this->option('name') ?: $this->ask('Nome completo');
        $email = strtolower((string) ($this->option('email') ?: $this->ask('Email')));
        $password = $this->secret('Palavra-passe (mín. 12 caracteres, maiúsculas, minúsculas, números e símbolos)');
        $confirm = $this->secret('Confirme a palavra-passe');

        $validator = Validator::make(compact('name', 'email', 'password') + ['password_confirmation' => $confirm], [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'status' => 'active', 'is_staff' => true]);
        $user->forceFill(['email_verified_at' => now(), 'password_changed_at' => now()])->save();
        $user->assignRole('super-admin');
        $audit->log('staff.created', $user, null, ['role' => 'super-admin'], 'Super Administrador criado via consola');

        $this->info("Super Administrador criado: {$email}");
        $this->line('Recomendação: active a autenticação de dois factores no primeiro acesso (Conta › Segurança).');
        $this->line('O Super Administrador não aprova crédito nem movimenta fundos: crie os restantes funcionários no painel.');

        return self::SUCCESS;
    }
}
