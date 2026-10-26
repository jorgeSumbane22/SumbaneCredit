<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditAssessment extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    public const RESULTS = [
        'favorable' => 'Indicadores favoráveis',
        'review' => 'Necessita análise adicional',
        'unfavorable' => 'Indicadores desfavoráveis',
    ];

    protected $fillable = [
        'loan_application_id', 'rule_set_id', 'rules_version', 'rules', 'inputs', 'indicators',
        'result', 'explanation', 'performed_by',
    ];

    protected function casts(): array
    {
        return ['rules' => 'array', 'inputs' => 'array', 'indicators' => 'array', 'created_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function resultLabel(): string
    {
        return self::RESULTS[$this->result] ?? $this->result;
    }

    public static function colorFor(string $status): string
    {
        return ['favorable' => 'success', 'review' => 'warning', 'unfavorable' => 'danger'][$status] ?? 'secondary';
    }
}
