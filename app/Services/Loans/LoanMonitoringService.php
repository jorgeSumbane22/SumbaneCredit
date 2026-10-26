<?php

namespace App\Services\Loans;

use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Notifications\InstallmentDueReminderNotification;
use App\Notifications\InstallmentOverdueNotification;
use App\Services\Collections\CollectionService;
use App\Services\NotificationDispatcher;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Tarefas diárias (Scheduler): detecção de prestações vencidas, penalizações,
 * processos de cobrança e avisos de vencimento — sem notificações duplicadas.
 */
class LoanMonitoringService
{
    public function __construct(
        private readonly LoanBalanceService $balances,
        private readonly PenaltyService $penalties,
        private readonly CollectionService $collections,
        private readonly NotificationDispatcher $notifier,
        private readonly SettingsService $settings,
    ) {}

    public function processOverdue(?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::today();
        $stats = ['loans' => 0, 'overdue_loans' => 0, 'penalties' => 0, 'notifications' => 0, 'errors' => 0];

        Loan::where('status', LoanStatus::Active)->with('client.user')->chunkById(100, function ($loans) use ($asOf, &$stats) {
            foreach ($loans as $loan) {
                try {
                    $stats['loans']++;
                    $this->balances->refreshInstallmentAging($loan, $asOf);
                    $stats['penalties'] += $this->penalties->applyLateFees($loan, $asOf);
                    $loan->refresh();
                    $this->balances->refresh($loan, $asOf);
                    $this->collections->syncLoan($loan);

                    if ($loan->days_past_due > 0) {
                        $stats['overdue_loans']++;
                        $stats['notifications'] += $this->notifyOverdue($loan);
                    }
                } catch (Throwable $e) {
                    $stats['errors']++;
                    report($e);
                }
            }
        });

        return $stats;
    }

    private function notifyOverdue(Loan $loan): int
    {
        $user = $loan->client?->user;
        if (! $user) {
            return 0;
        }
        $sent = 0;
        LoanInstallment::where('loan_id', $loan->id)->where('schedule_version', $loan->schedule_version)
            ->whereIn('status', InstallmentStatus::openValues())
            ->whereDate('due_date', '<', today())
            ->get()
            ->each(function ($installment) use ($user, $loan, &$sent) {
                // Um aviso por prestação ao 1.º dia de atraso e novo aviso a cada 7 dias.
                $bucket = intdiv(max($installment->days_overdue - 1, 0), 7);
                $key = "overdue:installment:{$installment->id}:{$bucket}";
                if ($this->notifier->once($key, $user, new InstallmentOverdueNotification($loan, $installment))) {
                    $sent++;
                }
            });

        return $sent;
    }

    public function sendDueReminders(?CarbonImmutable $asOf = null): int
    {
        $asOf ??= CarbonImmutable::today();
        $days = max(0, $this->settings->int('finance.due_reminder_days'));
        $target = $asOf->addDays($days)->toDateString();
        $sent = 0;

        LoanInstallment::with('loan.client.user')
            ->whereIn('status', InstallmentStatus::openValues())
            ->whereDate('due_date', '>=', $asOf->toDateString())
            ->whereDate('due_date', '<=', $target)
            ->whereHas('loan', fn ($q) => $q->where('status', LoanStatus::Active)->whereColumn('loans.schedule_version', 'loan_installments.schedule_version'))
            ->chunkById(200, function ($installments) use (&$sent) {
                foreach ($installments as $installment) {
                    $user = $installment->loan->client?->user;
                    if ($user && $this->notifier->once("due_reminder:installment:{$installment->id}", $user, new InstallmentDueReminderNotification($installment->loan, $installment))) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }
}
