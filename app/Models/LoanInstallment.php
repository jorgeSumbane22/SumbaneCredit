<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class LoanInstallment extends Model
{
    protected $fillable = [
        'loan_id', 'schedule_version', 'number', 'due_date', 'opening_balance', 'principal_due', 'interest_due',
        'fee_due', 'penalty_due', 'principal_paid', 'interest_paid', 'fee_paid', 'penalty_paid',
        'interest_waived', 'penalty_waived', 'total_due', 'total_paid', 'status', 'days_overdue', 'paid_at',
    ];

    protected $attributes = [
        'fee_due' => '0.00', 'penalty_due' => '0.00', 'principal_paid' => '0.00', 'interest_paid' => '0.00',
        'fee_paid' => '0.00', 'penalty_paid' => '0.00', 'interest_waived' => '0.00', 'penalty_waived' => '0.00',
        'total_paid' => '0.00', 'status' => 'pending', 'days_overdue' => 0, 'schedule_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'status' => InstallmentStatus::class,
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'opening_balance' => 'decimal:2',
            'principal_due' => 'decimal:2',
            'interest_due' => 'decimal:2',
            'fee_due' => 'decimal:2',
            'penalty_due' => 'decimal:2',
            'principal_paid' => 'decimal:2',
            'interest_paid' => 'decimal:2',
            'fee_paid' => 'decimal:2',
            'penalty_paid' => 'decimal:2',
            'interest_waived' => 'decimal:2',
            'penalty_waived' => 'decimal:2',
            'total_due' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'number' => 'integer',
            'days_overdue' => 'integer',
            'schedule_version' => 'integer',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function penalty(): HasOne
    {
        return $this->hasOne(Penalty::class);
    }

    /** @return array{principal:string, interest:string, fee:string, penalty:string} */
    public function outstandingComponents(): array
    {
        return [
            'principal' => Money::toDb(Money::sub($this->principal_due, $this->principal_paid)),
            'interest' => Money::toDb(Money::sub(Money::sub($this->interest_due, $this->interest_paid), $this->interest_waived)),
            'fee' => Money::toDb(Money::sub($this->fee_due, $this->fee_paid)),
            'penalty' => Money::toDb(Money::sub(Money::sub($this->penalty_due, $this->penalty_paid), $this->penalty_waived)),
        ];
    }

    public function outstandingTotal(): string
    {
        return Money::toDb(Money::add(...array_values($this->outstandingComponents())));
    }

    /** Aplica (sinal +1) ou reverte (sinal −1) uma imputação de pagamento. */
    public function applyAllocation(array $parts, int $sign = 1): void
    {
        foreach (['principal', 'interest', 'fee', 'penalty'] as $c) {
            $amount = Money::of($parts[$c] ?? '0');
            $field = "{$c}_paid";
            $new = $sign > 0 ? Money::add($this->{$field}, $amount) : Money::sub($this->{$field}, $amount);
            if (Money::lt($new, '0')) {
                throw new InvalidArgumentException("Imputação inválida: {$field} ficaria negativo.");
            }
            $this->{$field} = Money::toDb($new);
        }

        foreach ($this->outstandingComponents() as $c => $value) {
            if (Money::lt($value, '0')) {
                throw new InvalidArgumentException("Imputação inválida: componente {$c} excede o valor devido.");
            }
        }

        $this->recalculate();
    }

    /** Recalcula totais e estado a partir dos componentes. */
    public function recalculate(): void
    {
        if ($this->status === InstallmentStatus::Restructured) {
            return;
        }

        $this->total_due = Money::toDb(Money::sub(
            Money::sub(Money::add($this->principal_due, $this->interest_due, $this->fee_due, $this->penalty_due), $this->interest_waived),
            $this->penalty_waived,
        ));
        $this->total_paid = Money::toDb(Money::add($this->principal_paid, $this->interest_paid, $this->fee_paid, $this->penalty_paid));

        $outstanding = $this->outstandingTotal();
        if (Money::isZero($outstanding)) {
            $this->status = InstallmentStatus::Paid;
            $this->paid_at ??= now();
            // Preserva os dias de atraso verificados no pagamento (histórico interno).
            $due = $this->due_date?->copy()->startOfDay();
            $paid = $this->paid_at->copy()->startOfDay();
            $this->days_overdue = $due && $paid->gt($due) ? (int) $due->diffInDays($paid) : 0;
        } else {
            $this->status = Money::isPositive($this->total_paid) ? InstallmentStatus::Partial : InstallmentStatus::Pending;
            $this->paid_at = null;
        }
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [InstallmentStatus::Pending, InstallmentStatus::Partial], true)
            && $this->due_date->lt(today());
    }

    public function displayStatus(): array
    {
        if ($this->isOverdue()) {
            return ['label' => 'Em atraso', 'color' => 'danger'];
        }

        return ['label' => $this->status->label(), 'color' => $this->status->color()];
    }
}
