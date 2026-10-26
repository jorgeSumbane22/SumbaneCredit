<?php

namespace Tests\Feature;

use App\Models\CollectionCase;
use App\Models\Penalty;
use App\Notifications\InstallmentDueReminderNotification;
use App\Notifications\InstallmentOverdueNotification;
use App\Services\Loans\LoanMonitoringService;
use App\Services\Loans\PenaltyService;
use App\Services\Payments\ReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_processing_applies_penalty_once_and_opens_collection(): void
    {
        $client = $this->clientUser();
        $loan = $this->activeLoan($client, $this->product(['penalty_type' => 'fixed', 'penalty_value' => 100, 'penalty_grace_days' => 3]), '2026-01-15', ['amount' => '9000', 'term' => 3]);
        Notification::fake();
        $service = app(LoanMonitoringService::class);

        // Dentro da carência: sem penalização mas já em atraso
        $this->atDate('2026-02-17');
        $service->processOverdue(CarbonImmutable::parse('2026-02-17'));
        $this->assertSame(0, Penalty::count());
        $this->assertSame(2, $loan->fresh()->days_past_due);
        $this->assertSame(1, CollectionCase::open()->count());

        // Após a carência: uma penalização, mesmo processando várias vezes
        $this->atDate('2026-02-20');
        $service->processOverdue(CarbonImmutable::parse('2026-02-20'));
        $service->processOverdue(CarbonImmutable::parse('2026-02-20'));
        $this->assertSame(1, Penalty::count());
        $this->assertSame('100.00', (string) $loan->currentInstallments()->first()->penalty_due);
        $this->assertSame(1, CollectionCase::open()->count());
        Notification::assertSentToTimes($client, InstallmentOverdueNotification::class, 1);
        $this->assertTrue(app(ReconciliationService::class)->checkLoan($loan->fresh())['matched']);

        // Pagamento integral do valor vencido encerra o processo de cobrança
        $due = $loan->fresh()->currentInstallments()->first()->outstandingTotal();
        $this->pay($loan, $due, '2026-02-21');
        $this->assertSame(0, CollectionCase::open()->count());
        $this->assertSame('resolved', CollectionCase::first()->status);
        $this->assertSame(6, $loan->currentInstallments()->first()->days_overdue, 'Dias de atraso preservados no histórico');
    }

    public function test_penalty_waiver_is_recorded(): void
    {
        $loan = $this->activeLoan($this->clientUser(), $this->product(['penalty_type' => 'percent', 'penalty_value' => 10, 'penalty_grace_days' => 0]), '2026-01-15', ['amount' => '9000', 'term' => 3]);
        $this->atDate('2026-02-16');
        app(LoanMonitoringService::class)->processOverdue(CarbonImmutable::parse('2026-02-16'));
        $penalty = Penalty::firstOrFail();
        $this->assertTrue(\App\Support\Money::isPositive($penalty->amount));

        $gestor = $this->staff('gestor-credito');
        Auth::guard('web')->setUser($gestor);
        app(PenaltyService::class)->waive($penalty, $gestor, 'Atraso justificado por falha do prestador');
        $this->assertSame('waived', $penalty->fresh()->status);
        $this->assertSame((string) $penalty->amount, (string) $loan->currentInstallments()->first()->penalty_waived);
        $this->assertTrue(app(ReconciliationService::class)->checkLoan($loan->fresh())['matched']);
    }

    public function test_due_reminders_are_not_duplicated(): void
    {
        $client = $this->clientUser();
        $this->activeLoan($client, $this->product(), '2026-01-15', ['amount' => '9000', 'term' => 3]);
        Notification::fake();
        $this->atDate('2026-02-13');

        app(LoanMonitoringService::class)->sendDueReminders(CarbonImmutable::parse('2026-02-13'));
        app(LoanMonitoringService::class)->sendDueReminders(CarbonImmutable::parse('2026-02-13'));
        Notification::assertSentToTimes($client, InstallmentDueReminderNotification::class, 1);
    }

    public function test_promise_tracking(): void
    {
        $loan = $this->activeLoan($this->clientUser(), $this->product(['penalty_type' => 'none']), '2026-01-15', ['amount' => '9000', 'term' => 3]);
        $this->atDate('2026-02-20');
        app(LoanMonitoringService::class)->processOverdue(CarbonImmutable::parse('2026-02-20'));
        $case = CollectionCase::firstOrFail();
        $agent = $this->staff('agente-credito');

        $this->actingAs($agent)->post(route('admin.collections.contact', $case), [
            'channel' => 'call', 'outcome' => 'promise', 'contacted_at' => '2026-02-20 10:00', 'promised_amount' => 3450, 'promised_date' => '2026-02-25',
        ])->assertSessionHas('success');
        $this->assertSame('promised', $case->fresh()->status);

        $this->atDate('2026-02-27');
        $result = app(\App\Services\Collections\CollectionService::class)->checkPromises(CarbonImmutable::parse('2026-02-27'));
        $this->assertSame(['kept' => 0, 'broken' => 1], $result);
        $this->assertSame('open', $case->fresh()->status);
    }

    public function test_scheduled_commands_run(): void
    {
        $this->activeLoan($this->clientUser(), $this->product(), '2026-01-15');
        $this->atDate('2026-03-01');
        $this->artisan('loans:process-overdue', ['--date' => '2026-03-01'])->assertExitCode(0);
        $this->artisan('loans:send-reminders', ['--date' => '2026-03-01'])->assertExitCode(0);
        $this->artisan('collections:check-promises')->assertExitCode(0);
        $this->artisan('loans:reconcile')->assertExitCode(0);
    }
}
