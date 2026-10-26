<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = ['payment_id', 'loan_installment_id', 'principal', 'interest', 'fee', 'penalty', 'total', 'is_reversed'];

    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'interest' => 'decimal:2',
            'fee' => 'decimal:2',
            'penalty' => 'decimal:2',
            'total' => 'decimal:2',
            'is_reversed' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected function mutableColumns(): array
    {
        return ['is_reversed'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'loan_installment_id');
    }
}
