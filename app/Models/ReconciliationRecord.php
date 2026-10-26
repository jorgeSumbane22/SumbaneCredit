<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationRecord extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'type', 'loan_id', 'method', 'period_start', 'period_end', 'expected_amount', 'actual_amount',
        'difference', 'status', 'details', 'notes', 'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'details' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
