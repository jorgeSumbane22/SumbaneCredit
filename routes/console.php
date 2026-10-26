<?php

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Services\Collections\CollectionService;
use App\Services\Loans\LoanMonitoringService;
use App\Services\Payments\ReconciliationService;
use App\Support\PermissionSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Comandos do SumbaneCredit
|--------------------------------------------------------------------------
*/

Artisan::command('loans:process-overdue {--date= : Data de referência (AAAA-MM-DD)}', function (LoanMonitoringService $monitoring) {
    $date = $this->option('date') ? CarbonImmutable::parse($this->option('date')) : CarbonImmutable::today();
    $stats = $monitoring->processOverdue($date);
    $this->info(sprintf('Processados %d empréstimos activos em %s: %d em atraso, %d penalizações aplicadas, %d avisos enviados, %d erros.',
        $stats['loans'], $date->format('d/m/Y'), $stats['overdue_loans'], $stats['penalties'], $stats['notifications'], $stats['errors']));

    return $stats['errors'] > 0 ? 1 : 0;
})->purpose('Actualiza atrasos, aplica penalizações contratuais, abre/fecha cobranças e notifica atrasos');

Artisan::command('loans:send-reminders {--date=}', function (LoanMonitoringService $monitoring) {
    $date = $this->option('date') ? CarbonImmutable::parse($this->option('date')) : CarbonImmutable::today();
    $sent = $monitoring->sendDueReminders($date);
    $this->info("Avisos de vencimento enviados: {$sent}");
})->purpose('Envia avisos de próximas prestações (sem duplicados)');

Artisan::command('collections:check-promises {--date=}', function (CollectionService $collections) {
    $date = $this->option('date') ? CarbonImmutable::parse($this->option('date')) : CarbonImmutable::today();
    $r = $collections->checkPromises($date);
    $this->info("Promessas cumpridas: {$r['kept']} · não cumpridas: {$r['broken']}");
})->purpose('Verifica o cumprimento das promessas de pagamento vencidas');

Artisan::command('loans:reconcile', function (ReconciliationService $reconciliation) {
    $mismatches = 0;
    Loan::whereIn('status', [LoanStatus::Active, LoanStatus::Settled])->chunkById(100, function ($loans) use ($reconciliation, &$mismatches) {
        foreach ($loans as $loan) {
            $check = $reconciliation->checkLoan($loan);
            if (! $check['matched']) {
                $mismatches++;
                $reconciliation->reconcileLoan($loan, null, 'Reconciliação automática diária');
                $this->warn("Diferenças no empréstimo {$loan->loan_number}");
            }
        }
    });
    $this->info($mismatches ? "{$mismatches} empréstimo(s) com diferenças (registados)." : 'Todos os empréstimos reconciliados sem diferenças.');

    return $mismatches ? 1 : 0;
})->purpose('Verifica a consistência entre saldos, prestações, razão e pagamentos');

Artisan::command('sumbanecredit:sync-permissions {--reset : Repõe a matriz de permissões por omissão}', function () {
    PermissionSync::run((bool) $this->option('reset'));
    $this->info('Permissões e perfis sincronizados.');
})->purpose('Cria/actualiza permissões e perfis do sistema');

/*
|--------------------------------------------------------------------------
| Agendamento (executar "php artisan schedule:work" ou cron "schedule:run")
|--------------------------------------------------------------------------
*/
Schedule::command('loans:process-overdue')->dailyAt('00:30')->timezone('Africa/Maputo')->withoutOverlapping()->onOneServer();
Schedule::command('collections:check-promises')->dailyAt('01:00')->timezone('Africa/Maputo')->withoutOverlapping();
Schedule::command('loans:send-reminders')->dailyAt('08:00')->timezone('Africa/Maputo')->withoutOverlapping();
Schedule::command('loans:reconcile')->dailyAt('02:00')->timezone('Africa/Maputo')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->weekly();
