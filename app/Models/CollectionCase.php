<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Processo de cobrança (tabela "collections"). */
class CollectionCase extends Model
{
    protected $table = 'collections';

    public const STATUSES = [
        'open' => 'Em aberto',
        'promised' => 'Promessa de pagamento',
        'resolved' => 'Regularizado',
        'closed' => 'Encerrado',
    ];

    public const PRIORITIES = ['low' => 'Baixa', 'normal' => 'Normal', 'high' => 'Alta'];

    protected $fillable = [
        'loan_id', 'client_id', 'status', 'priority', 'assigned_to', 'days_past_due', 'overdue_amount',
        'opened_at', 'closed_at', 'last_contact_at', 'next_action_at', 'notes', 'open_loan_key',
    ];

    protected function casts(): array
    {
        return [
            'overdue_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_contact_at' => 'datetime',
            'next_action_at' => 'datetime',
            'days_past_due' => 'integer',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CollectionContact::class, 'collection_id')->latest('contacted_at');
    }

    public function promises(): HasMany
    {
        return $this->hasMany(PaymentPromise::class, 'collection_id')->latest('promised_date');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'promised']);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return ['open' => 'danger', 'promised' => 'warning', 'resolved' => 'success', 'closed' => 'secondary'][$this->status] ?? 'secondary';
    }

    public function agingBucket(): string
    {
        return match (true) {
            $this->days_past_due <= 30 => '1–30 dias',
            $this->days_past_due <= 60 => '31–60 dias',
            $this->days_past_due <= 90 => '61–90 dias',
            default => '+90 dias',
        };
    }
}
