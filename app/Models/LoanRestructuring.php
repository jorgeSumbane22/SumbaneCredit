<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRestructuring extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'loan_id', 'previous_schedule_version', 'new_schedule_version', 'outstanding_principal', 'capitalized_amount',
        'new_principal', 'new_term', 'new_interest_rate', 'new_amortization_method', 'new_repayment_frequency',
        'first_due_date', 'reason', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'outstanding_principal' => 'decimal:2',
            'capitalized_amount' => 'decimal:2',
            'new_principal' => 'decimal:2',
            'new_interest_rate' => 'decimal:4',
            'first_due_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
