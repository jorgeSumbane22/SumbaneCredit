<?php

namespace App\Models;

use App\Enums\DisbursementStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Disbursement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DisbursementStatus::class,
            'method' => PaymentMethod::class,
            'gross_amount' => 'decimal:2',
            'deducted_fees' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'authorized_at' => 'datetime',
            'completed_at' => 'datetime',
            'disbursed_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new LogicException('Desembolsos não podem ser apagados; use o cancelamento.');
        });
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
