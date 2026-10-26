<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Movimento do razão do empréstimo (imutável).
 * Débito: aumenta o valor devido pelo cliente. Crédito: reduz.
 * Componente "cash" regista entradas/saídas de fundos (desembolso líquido) sem afectar saldos.
 */
class FinancialTransaction extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    public const TYPES = [
        'disbursement' => 'Desembolso (capital)',
        'disbursement_cash_out' => 'Saída de fundos (desembolso líquido)',
        'interest_charge' => 'Juros contratuais',
        'fee_charge' => 'Encargo contratual',
        'upfront_fee_deduction' => 'Encargo deduzido no desembolso',
        'penalty_charge' => 'Penalização por atraso',
        'repayment' => 'Pagamento',
        'interest_waiver' => 'Dispensa de juros (liquidação antecipada)',
        'penalty_waiver' => 'Dispensa de penalização',
        'payment_reversal' => 'Estorno de pagamento',
        'restructure_close' => 'Encerramento do plano (reestruturação)',
        'restructure_open' => 'Novo plano (reestruturação)',
    ];

    public const COMPONENTS = [
        'principal' => 'Capital', 'interest' => 'Juros', 'fee' => 'Encargos', 'penalty' => 'Penalizações', 'cash' => 'Fundos',
    ];

    protected $fillable = [
        'transaction_number', 'loan_id', 'client_id', 'type', 'component', 'direction', 'amount',
        'source_type', 'source_id', 'description', 'posted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'posted_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function componentLabel(): string
    {
        return self::COMPONENTS[$this->component] ?? $this->component;
    }
}
