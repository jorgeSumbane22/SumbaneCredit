<?php

namespace App\Services\Collections;

use App\Enums\LoanStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CollectionCase;
use App\Models\Loan;
use App\Models\PaymentPromise;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SettingsService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cobranças: abertura/encerramento automático de processos para empréstimos em atraso,
 * registo de contactos e promessas de pagamento.
 */
class CollectionService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly SettingsService $settings,
    ) {}

    public function syncLoan(Loan $loan): ?CollectionCase
    {
        $case = CollectionCase::where('open_loan_key', $loan->id)->first();
        $overdue = $loan->status === LoanStatus::Active && $loan->days_past_due > 0;

        if ($overdue) {
            $amount = Money::toDb((string) $loan->currentInstallments()
                ->whereIn('status', ['pending', 'partial'])
                ->whereDate('due_date', '<', today())
                ->get()
                ->reduce(fn ($carry, $i) => Money::add($carry, $i->outstandingTotal()), '0'));

            $attributes = [
                'days_past_due' => $loan->days_past_due,
                'overdue_amount' => $amount,
                'priority' => $loan->days_past_due > 60 ? 'high' : ($loan->days_past_due > 15 ? 'normal' : 'low'),
            ];

            if (! $case) {
                $case = CollectionCase::create($attributes + [
                    'loan_id' => $loan->id,
                    'client_id' => $loan->client_id,
                    'status' => 'open',
                    'opened_at' => now(),
                    'next_action_at' => now(),
                    'assigned_to' => $this->settings->bool('finance.collection_auto_assign') ? $loan->loan_officer_id : null,
                    'open_loan_key' => $loan->id,
                ]);
                $this->audit->log('collection.opened', $case, null, $attributes);
            } else {
                $case->update($attributes);
            }

            return $case;
        }

        if ($case) {
            $case->update([
                'status' => 'resolved', 'closed_at' => now(), 'open_loan_key' => null,
                'days_past_due' => 0, 'overdue_amount' => '0.00',
            ]);
            $case->promises()->where('status', 'pending')->update(['status' => 'kept', 'resolved_at' => now()]);
            $this->audit->log('collection.resolved', $case, null, ['status' => 'resolved']);
        }

        return $case;
    }

    public function logContact(CollectionCase $case, User $user, array $data): void
    {
        DB::transaction(function () use ($case, $user, $data) {
            $contact = $case->contacts()->create([
                'user_id' => $user->id,
                'channel' => $data['channel'],
                'outcome' => $data['outcome'],
                'notes' => $data['notes'] ?? null,
                'contacted_at' => $data['contacted_at'] ?? now(),
                'next_action_at' => $data['next_action_at'] ?? null,
            ]);
            $case->update([
                'last_contact_at' => $contact->contacted_at,
                'next_action_at' => $data['next_action_at'] ?? $case->next_action_at,
            ]);

            if ($data['outcome'] === 'promise' && ! empty($data['promised_amount']) && ! empty($data['promised_date'])) {
                $this->createPromise($case, $user, (string) $data['promised_amount'], $data['promised_date'], $data['notes'] ?? null);
            }

            $this->audit->log('collection.contact', $case, null, ['channel' => $data['channel'], 'outcome' => $data['outcome']]);
        });
    }

    public function createPromise(CollectionCase $case, User $user, string $amount, string $date, ?string $notes): PaymentPromise
    {
        if (! in_array($case->status, ['open', 'promised'], true)) {
            throw new BusinessRuleException('O processo de cobrança está encerrado.');
        }
        if (CarbonImmutable::parse($date)->lt(CarbonImmutable::today())) {
            throw new BusinessRuleException('A data prometida não pode ser anterior a hoje.');
        }
        $promise = $case->promises()->create([
            'loan_id' => $case->loan_id,
            'promised_amount' => Money::toDb($amount),
            'promised_date' => $date,
            'status' => 'pending',
            'notes' => $notes,
            'created_by' => $user->id,
        ]);
        $case->update(['status' => 'promised', 'next_action_at' => CarbonImmutable::parse($date)->addDay()]);
        $this->audit->log('collection.promise', $promise, null, ['amount' => $promise->promised_amount, 'date' => $date]);

        return $promise;
    }

    /** Verifica promessas vencidas: cumpridas se os pagamentos confirmados cobrirem o valor prometido. */
    public function checkPromises(CarbonImmutable $asOf): array
    {
        $kept = 0;
        $broken = 0;
        PaymentPromise::with('collection')->where('status', 'pending')
            ->whereDate('promised_date', '<', $asOf->toDateString())
            ->get()
            ->each(function (PaymentPromise $promise) use (&$kept, &$broken) {
                $paid = (string) \App\Models\Payment::where('loan_id', $promise->loan_id)
                    ->where('status', PaymentStatus::Confirmed)
                    ->whereBetween('paid_at', [$promise->created_at->toDateString(), $promise->promised_date->toDateString()])
                    ->sum('amount');
                $isKept = Money::gte($paid, (string) $promise->promised_amount);
                $promise->update(['status' => $isKept ? 'kept' : 'broken', 'resolved_at' => now()]);
                if ($promise->collection && $promise->collection->status === 'promised') {
                    $promise->collection->update(['status' => 'open', 'next_action_at' => now()]);
                }
                $isKept ? $kept++ : $broken++;
            });

        return compact('kept', 'broken');
    }

    public function assign(CollectionCase $case, ?User $assignee, User $by): void
    {
        $case->update(['assigned_to' => $assignee?->id]);
        $this->audit->log('collection.assigned', $case, null, ['assigned_to' => $assignee?->id]);
    }

    public function close(CollectionCase $case, User $user, string $notes): void
    {
        $case->update([
            'status' => 'closed', 'closed_at' => now(), 'open_loan_key' => null,
            'notes' => trim(($case->notes ? $case->notes."\n" : '').$notes),
        ]);
        $this->audit->log('collection.closed', $case, null, ['status' => 'closed'], $notes);
    }
}
