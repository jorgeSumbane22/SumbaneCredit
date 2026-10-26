<?php

namespace App\Models;

use App\Enums\AmortizationMethod;
use App\Enums\RepaymentFrequency;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Versão imutável das condições financeiras de um produto.
 * Uma vez usada num pedido ou empréstimo, nunca é alterada: alterações criam nova versão.
 */
class LoanProductVersion extends Model
{
    protected $fillable = [
        'loan_product_id', 'version', 'min_amount', 'max_amount', 'min_term', 'max_term',
        'interest_rate', 'rate_period', 'allowed_frequencies', 'amortization_methods', 'fees',
        'penalty_type', 'penalty_value', 'penalty_grace_days', 'early_settlement_rule',
        'eligibility_rules', 'change_notes', 'effective_from', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'penalty_value' => 'decimal:4',
            'allowed_frequencies' => 'array',
            'amortization_methods' => 'array',
            'fees' => 'array',
            'eligibility_rules' => 'array',
            'effective_from' => 'datetime',
            'min_term' => 'integer',
            'max_term' => 'integer',
            'penalty_grace_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            throw new LogicException('As versões de produto são imutáveis. Crie uma nova versão.');
        });
        static::deleting(function () {
            throw new LogicException('As versões de produto não podem ser apagadas.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    /** @return RepaymentFrequency[] */
    public function frequencies(): array
    {
        return array_map(fn ($f) => RepaymentFrequency::from($f), $this->allowed_frequencies ?? ['monthly']);
    }

    /** @return AmortizationMethod[] */
    public function methods(): array
    {
        return array_map(fn ($m) => AmortizationMethod::from($m), $this->amortization_methods ?? ['annuity']);
    }

    public function ratePeriodLabel(): string
    {
        return $this->rate_period === 'monthly' ? 'ao mês' : 'ao ano';
    }

    public function rateLabel(): string
    {
        return str_replace('.', ',', rtrim(rtrim((string) $this->interest_rate, '0'), '.')).'% '.$this->ratePeriodLabel();
    }

    public function penaltyLabel(): string
    {
        return match ($this->penalty_type) {
            'fixed' => Money::format($this->penalty_value).' por prestação em atraso (após '.$this->penalty_grace_days.' dia(s) de carência)',
            'percent' => str_replace('.', ',', rtrim(rtrim((string) $this->penalty_value, '0'), '.')).'% do valor em atraso, uma vez por prestação (após '.$this->penalty_grace_days.' dia(s) de carência)',
            default => 'Sem penalizações por atraso',
        };
    }

    public function earlySettlementLabel(): string
    {
        return $this->early_settlement_rule === 'full_contractual'
            ? 'Liquidação antecipada com pagamento integral dos juros contratados'
            : 'Liquidação antecipada sem juros dos períodos futuros (apenas juros do período em curso)';
    }

    public function feesLabel(): string
    {
        if (empty($this->fees)) {
            return 'Sem encargos adicionais';
        }

        return collect($this->fees)->map(function ($fee) {
            $value = $fee['type'] === 'percent'
                ? str_replace('.', ',', (string) (float) $fee['value']).'% do capital'
                : Money::format($fee['value']);
            $charge = ($fee['charge'] ?? 'upfront') === 'financed' ? 'repartido pelas prestações' : 'deduzido no desembolso';

            return "{$fee['name']}: {$value} ({$charge})";
        })->implode('; ');
    }

    public function eligibility(string $key, mixed $default = null): mixed
    {
        return data_get($this->eligibility_rules, $key, $default);
    }
}
