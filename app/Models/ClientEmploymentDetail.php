<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientEmploymentDetail extends Model
{
    protected $fillable = [
        'client_id', 'employment_type', 'employer_name', 'position', 'employer_phone', 'employer_address',
        'start_date', 'monthly_income', 'other_income', 'monthly_expenses',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'monthly_income' => 'decimal:2',
            'other_income' => 'decimal:2',
            'monthly_expenses' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function employmentTypeLabel(): string
    {
        return Client::EMPLOYMENT_TYPES[$this->employment_type] ?? '—';
    }
}
