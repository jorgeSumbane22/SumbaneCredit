<?php

namespace App\Models;

use App\Enums\AmortizationMethod;
use App\Enums\ApplicationStatus;
use App\Enums\RepaymentFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_number', 'client_id', 'loan_product_id', 'loan_product_version_id', 'requested_amount',
        'term', 'repayment_frequency', 'amortization_method', 'purpose', 'monthly_income', 'other_income',
        'monthly_expenses', 'existing_obligations', 'employment_type', 'employer_name', 'occupation',
        'simulated_installment', 'simulated_total', 'status', 'assigned_to', 'consents',
        'declaration_accepted_at', 'submitted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'repayment_frequency' => RepaymentFrequency::class,
            'amortization_method' => AmortizationMethod::class,
            'requested_amount' => 'decimal:2',
            'monthly_income' => 'decimal:2',
            'other_income' => 'decimal:2',
            'monthly_expenses' => 'decimal:2',
            'existing_obligations' => 'decimal:2',
            'simulated_installment' => 'decimal:2',
            'simulated_total' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'consents' => 'array',
            'declaration_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'term' => 'integer',
            'approved_term' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function productVersion(): BelongsTo
    {
        return $this->belongsTo(LoanProductVersion::class, 'loan_product_version_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(LoanApplicationReview::class)->latest('id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(CreditAssessment::class)->latest('id');
    }

    public function latestAssessment(): HasOne
    {
        return $this->hasOne(CreditAssessment::class)->latestOfMany();
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(ClientDocument::class, 'loan_application_documents')->withPivot('created_at');
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(Guarantor::class);
    }

    public function collaterals(): HasMany
    {
        return $this->hasMany(Collateral::class);
    }

    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ApplicationStatus::openValues());
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('application_number', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($c) => $c->search($term));
        });
    }

    public function totalIncome(): string
    {
        return \App\Support\Money::add($this->monthly_income, $this->other_income);
    }
}
