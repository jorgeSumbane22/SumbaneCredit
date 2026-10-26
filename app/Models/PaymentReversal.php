<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReversal extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = ['payment_id', 'amount', 'reason', 'reversed_by', 'reversed_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'reversed_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
