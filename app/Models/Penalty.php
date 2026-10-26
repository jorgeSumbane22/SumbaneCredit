<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Penalty extends Model
{
    protected $fillable = [
        'loan_id', 'loan_installment_id', 'type', 'amount', 'applied_on', 'status', 'waived_by', 'waived_at', 'waiver_reason',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'applied_on' => 'date', 'waived_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Penalizações não podem ser apagadas; use a dispensa.'));
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'loan_installment_id');
    }

    public function waiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }
}
