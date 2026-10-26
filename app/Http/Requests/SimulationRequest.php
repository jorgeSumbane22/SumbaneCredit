<?php

namespace App\Http\Requests;

use App\Enums\AmortizationMethod;
use App\Enums\RepaymentFrequency;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'product_id' => $this->input('product_id', $this->input('loan_product_id')),
            'amount' => Money::parseInput($this->input('amount')) ?? $this->input('amount'),
        ]);
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:loan_products,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'term' => ['required', 'integer', 'min:1', 'max:600'],
            'frequency' => ['nullable', Rule::enum(RepaymentFrequency::class)],
            'method' => ['nullable', Rule::enum(AmortizationMethod::class)],
        ];
    }

    public function attributes(): array
    {
        return ['product_id' => 'produto', 'amount' => 'montante', 'term' => 'número de prestações'];
    }
}
