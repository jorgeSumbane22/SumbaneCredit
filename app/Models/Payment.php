<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'allocated_amount' => 'decimal:2',
            'paid_at' => 'date',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new LogicException('Pagamentos não podem ser apagados; use a rejeição ou o estorno.');
        });
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(PaymentReversal::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Confirmed);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('payment_number', 'like', "%{$term}%")
                ->orWhere('receipt_number', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhereHas('loan', fn ($l) => $l->where('loan_number', 'like', "%{$term}%"))
                ->orWhereHas('client', fn ($c) => $c->search($term));
        });
    }

    public function typeLabel(): string
    {
        return $this->type === 'settlement' ? 'Liquidação antecipada' : 'Pagamento';
    }

    public function sourceLabel(): string
    {
        return ['staff' => 'Balcão/Funcionário', 'client_portal' => 'Portal do cliente', 'gateway' => 'Integração'][$this->source] ?? $this->source;
    }
}
