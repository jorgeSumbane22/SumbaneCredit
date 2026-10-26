<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collateral extends Model
{
    public const TYPES = [
        'vehicle' => 'Veículo',
        'property' => 'Imóvel',
        'equipment' => 'Equipamento',
        'deposit' => 'Depósito/Poupança',
        'other' => 'Outro',
    ];

    public const STATUSES = [
        'declared' => 'Declarada',
        'verified' => 'Verificada',
        'released' => 'Libertada',
        'executed' => 'Executada',
    ];

    protected $fillable = [
        'loan_application_id', 'loan_id', 'type', 'description', 'estimated_value', 'ownership_document',
        'status', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['estimated_value' => 'decimal:2', 'verified_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
