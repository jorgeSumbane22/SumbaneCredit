<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guarantor extends Model
{
    protected $fillable = [
        'loan_application_id', 'loan_id', 'full_name', 'document_type', 'document_number', 'phone',
        'relationship', 'occupation', 'monthly_income', 'address', 'status',
    ];

    protected function casts(): array
    {
        return ['monthly_income' => 'decimal:2'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
