<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentPromise extends Model
{
    public const STATUSES = [
        'pending' => 'Pendente', 'kept' => 'Cumprida', 'broken' => 'Não cumprida', 'cancelled' => 'Cancelada',
    ];

    protected $fillable = ['collection_id', 'loan_id', 'promised_amount', 'promised_date', 'status', 'notes', 'created_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['promised_amount' => 'decimal:2', 'promised_date' => 'date', 'resolved_at' => 'datetime'];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(CollectionCase::class, 'collection_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return ['pending' => 'warning', 'kept' => 'success', 'broken' => 'danger', 'cancelled' => 'secondary'][$this->status] ?? 'secondary';
    }
}
