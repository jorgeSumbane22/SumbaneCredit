<?php

namespace App\Models;

use App\Enums\AmortizationMethod;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\RepaymentFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Loan extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'repayment_frequency' => RepaymentFrequency::class,
            'amortization_method' => AmortizationMethod::class,
            'principal_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'penalty_value' => 'decimal:4',
            'upfront_fees' => 'decimal:2',
            'financed_fees' => 'decimal:2',
            'total_interest' => 'decimal:2',
            'total_contract_amount' => 'decimal:2',
            'principal_outstanding' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'total_outstanding' => 'decimal:2',
            'fees' => 'array',
            'terms_snapshot' => 'array',
            'next_due_date' => 'date',
            'first_due_date' => 'date',
            'maturity_date' => 'date',
            'disbursed_at' => 'date',
            'approved_at' => 'datetime',
            'contract_signed_at' => 'datetime',
            'settled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'term' => 'integer',
            'schedule_version' => 'integer',
            'days_past_due' => 'integer',
            'penalty_grace_days' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function productVersion(): BelongsTo
    {
        return $this->belongsTo(LoanProductVersion::class, 'loan_product_version_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loan_officer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Todas as prestações (incluindo versões reestruturadas). */
    public function allInstallments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('schedule_version')->orderBy('number');
    }

    /** Prestações do plano em vigor (usar em instâncias carregadas). */
    public function currentInstallments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)
            ->where('schedule_version', $this->schedule_version)
            ->orderBy('number');
    }

    public function openInstallments(): HasMany
    {
        return $this->currentInstallments()->whereIn('status', InstallmentStatus::openValues());
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class)->latest('id');
    }

    public function disbursement(): HasOne
    {
        return $this->hasOne(Disbursement::class)->where('status', 'completed');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class)->orderBy('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LoanStatusHistory::class)->latest('id');
    }

    public function restructurings(): HasMany
    {
        return $this->hasMany(LoanRestructuring::class)->latest('id');
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(Guarantor::class);
    }

    public function collaterals(): HasMany
    {
        return $this->hasMany(Collateral::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(CollectionCase::class)->latest('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LoanStatus::Active);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', LoanStatus::Active)->where('days_past_due', '>', 0);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('loan_number', 'like', "%{$term}%")
                ->orWhere('contract_number', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($c) => $c->search($term));
        });
    }

    public function isActive(): bool
    {
        return $this->status === LoanStatus::Active;
    }

    public function rateLabel(): string
    {
        return str_replace('.', ',', rtrim(rtrim((string) $this->interest_rate, '0'), '.')).'% '.($this->rate_period === 'monthly' ? 'ao mês' : 'ao ano');
    }

    public function nextInstallment(): ?LoanInstallment
    {
        return $this->openInstallments()->orderBy('due_date')->first();
    }
}
