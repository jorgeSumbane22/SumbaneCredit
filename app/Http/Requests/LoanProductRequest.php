<?php

namespace App\Http\Requests;

use App\Enums\AmortizationMethod;
use App\Enums\RepaymentFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_public' => $this->boolean('is_public'),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'fees' => array_values(array_filter((array) $this->input('fees', []), fn ($f) => ! empty($f['name']))),
        ]);
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('loan_products', 'code')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:3000'],
            'requirements' => ['nullable', 'string', 'max:3000'],
            'contract_conditions' => ['nullable', 'string', 'max:6000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_public' => ['boolean'],
            'min_amount' => ['required', 'numeric', 'min:1'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount', 'max:999999999'],
            'min_term' => ['required', 'integer', 'min:1', 'max:600'],
            'max_term' => ['required', 'integer', 'gte:min_term', 'max:600'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:1000'],
            'rate_period' => ['required', Rule::in(['monthly', 'annual'])],
            'allowed_frequencies' => ['required', 'array', 'min:1'],
            'allowed_frequencies.*' => [Rule::enum(RepaymentFrequency::class)],
            'amortization_methods' => ['required', 'array', 'min:1'],
            'amortization_methods.*' => [Rule::enum(AmortizationMethod::class)],
            'fees' => ['array', 'max:6'],
            'fees.*.name' => ['required', 'string', 'max:80'],
            'fees.*.type' => ['required', Rule::in(['fixed', 'percent'])],
            'fees.*.value' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'fees.*.charge' => ['required', Rule::in(['upfront', 'financed'])],
            'penalty_type' => ['required', Rule::in(['none', 'fixed', 'percent'])],
            'penalty_value' => ['nullable', 'required_unless:penalty_type,none', 'numeric', 'min:0', 'max:999999999'],
            'penalty_grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'early_settlement_rule' => ['required', Rule::in(['waive_future_interest', 'full_contractual'])],
            'eligibility.min_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'eligibility.max_active_loans' => ['nullable', 'integer', 'min:1', 'max:20'],
            'eligibility.min_age' => ['nullable', 'integer', 'min:18', 'max:100'],
            'eligibility.requires_guarantor' => ['nullable', 'boolean'],
            'eligibility.requires_collateral' => ['nullable', 'boolean'],
            'change_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'min_amount' => 'montante mínimo', 'max_amount' => 'montante máximo', 'min_term' => 'prazo mínimo',
            'max_term' => 'prazo máximo', 'interest_rate' => 'taxa de juro', 'allowed_frequencies' => 'periodicidades',
            'amortization_methods' => 'métodos de amortização', 'penalty_value' => 'valor da penalização',
        ];
    }
}
