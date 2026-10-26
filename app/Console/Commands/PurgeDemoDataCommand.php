<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Remove TODOS os dados operacionais e de demonstração, deixando a base pronta para produção.
 *
 * Mantém: estrutura, migrations, perfis e permissões, definições do sistema, perguntas
 * frequentes e regras de avaliação (estas voltam a "por validar").
 * Remove: utilizadores, clientes, produtos, pedidos, empréstimos, pagamentos, razão, cobranças,
 * suporte, mensagens, notificações, auditoria, sessões e ficheiros privados carregados.
 */
class PurgeDemoDataCommand extends Command
{
    protected $signature = 'sumbanecredit:purge-demo {--force : Não pedir confirmação}';

    protected $description = 'Apaga todos os dados de demonstração/operacionais antes de colocar o sistema em produção';

    /** Tabelas esvaziadas (ordem indiferente: as chaves estrangeiras são desligadas). */
    private const TABLES = [
        'payment_allocations', 'payment_reversals', 'payments', 'financial_transactions', 'reconciliation_records',
        'penalties', 'disbursements', 'payment_promises', 'collection_contacts', 'collections',
        'loan_restructurings', 'loan_status_histories', 'loan_installments', 'guarantors', 'collaterals', 'loans',
        'credit_assessments', 'loan_application_reviews', 'loan_application_documents', 'loan_applications',
        'loan_product_versions', 'loan_products',
        'client_documents', 'client_employment_details', 'client_addresses', 'clients',
        'ticket_replies', 'support_tickets', 'contact_messages', 'testimonials',
        'notifications', 'notification_logs', 'idempotency_keys', 'audit_logs', 'sequences',
        'model_has_roles', 'model_has_permissions', 'users',
        'sessions', 'password_reset_tokens', 'jobs', 'failed_jobs', 'job_batches', 'cache', 'cache_locks',
    ];

    public function handle(): int
    {
        $db = DB::connection()->getDatabaseName();
        $this->warn("Vai APAGAR todos os utilizadores, clientes, produtos, empréstimos, pagamentos e auditoria da base \"{$db}\".");
        $this->line('Mantém-se: estrutura, perfis/permissões, definições, perguntas frequentes e regras de avaliação.');

        if (! $this->option('force') && ! $this->confirm('Confirma? Esta operação não pode ser desfeita.', false)) {
            $this->info('Operação cancelada.');

            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (self::TABLES as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::table($table)->truncate();
                    $this->line("  esvaziada: {$table}");
                }
            }
            // Regras de avaliação: mantidas, mas voltam a exigir validação na instituição.
            DB::table('credit_assessment_rule_sets')->update([
                'is_active' => false, 'validated_by' => null, 'validated_at' => null, 'created_by' => null,
                'name' => 'Parâmetros de referência (a calibrar e validar pela instituição)',
            ]);
            DB::table('system_settings')->update(['updated_by' => null]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Ficheiros privados carregados (documentos, comprovativos, contratos, anexos).
        foreach (['clients', 'loans', 'tickets'] as $dir) {
            Storage::disk('local')->deleteDirectory($dir);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::flush();

        $this->newLine();
        $this->info('Dados removidos. Próximos passos:');
        $this->line('  1. php artisan sumbanecredit:create-admin   (criar o Super Administrador)');
        $this->line('  2. Entrar no painel e preencher Definições › Instituição');
        $this->line('  3. Criar funcionários, produtos de crédito e validar as regras de avaliação');

        return self::SUCCESS;
    }
}
