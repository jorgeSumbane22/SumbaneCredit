<?php

namespace App\Http\Requests;

use App\Enums\AmortizationMethod;
use App\Enums\RepaymentFrequency;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Collateral;
use App\Services\DocumentStorageService;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // a autorização é feita pelas rotas/políticas
    }

    protected function prepareForValidation(): void
    {
        $money = fn ($v) => Money::parseInput($v) ?? $v;
        $this->merge([
            'amount' => $money($this->input('amount')),
            'monthly_income' => $money($this->input('monthly_income')),
            'other_income' => $money($this->input('other_income') ?: '0'),
            'monthly_expenses' => $money($this->input('monthly_expenses') ?: '0'),
            'existing_obligations' => $money($this->input('existing_obligations') ?: '0'),
            'guarantors' => array_values(array_filter((array) $this->input('guarantors', []), fn ($g) => ! empty($g['full_name']))),
            'collaterals' => array_values(array_filter((array) $this->input('collaterals', []), fn ($c) => ! empty($c['description']))),
        ]);
    }

    public function rules(): array
    {
        $file = DocumentStorageService::validationRule();

        return [
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'term' => ['required', 'integer', 'min:1', 'max:600'],
            'frequency' => ['required', Rule::enum(RepaymentFrequency::class)],
            'method' => ['required', Rule::enum(AmortizationMethod::class)],
            'purpose' => ['required', 'string', 'min:10', 'max:1000'],
            'monthly_income' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'other_income' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'monthly_expenses' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'existing_obligations' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'employment_type' => ['required', Rule::in(array_keys(Client::EMPLOYMENT_TYPES))],
            'employer_name' => ['nullable', 'string', 'max:150'],
            'occupation' => ['nullable', 'string', 'max:150'],

            'guarantors' => ['array', 'max:3'],
            'guarantors.*.full_name' => ['required', 'string', 'max:150'],
            'guarantors.*.document_type' => ['nullable', Rule::in(array_keys(Client::DOCUMENT_TYPES))],
            'guarantors.*.document_number' => ['required', 'string', 'max:50'],
            'guarantors.*.phone' => ['required', 'string', 'max:30'],
            'guarantors.*.relationship' => ['nullable', 'string', 'max:60'],
            'guarantors.*.occupation' => ['nullable', 'string', 'max:150'],
            'guarantors.*.monthly_income' => ['nullable', 'numeric', 'min:0'],
            'guarantors.*.address' => ['nullable', 'string', 'max:255'],

            'collaterals' => ['array', 'max:5'],
            'collaterals.*.type' => ['required', Rule::in(array_keys(Collateral::TYPES))],
            'collaterals.*.description' => ['required', 'string', 'max:255'],
            'collaterals.*.estimated_value' => ['required', 'numeric', 'min:0'],

            'document_ids' => ['array'],
            'document_ids.*' => ['integer'],
            'new_documents' => ['array', 'max:6'],
            'new_documents.*.type' => ['required_with:new_documents.*.file', Rule::in(array_keys(ClientDocument::TYPES))],
            'new_documents.*.file' => ['nullable', ...explode('|', $file)],

            'declaration' => ['accepted'],
            'consent_data_processing' => ['accepted'],
            'consent_credit_check' => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'loan_product_id' => 'produto', 'amount' => 'montante', 'term' => 'número de prestações',
            'frequency' => 'periodicidade', 'method' => 'método de amortização', 'purpose' => 'finalidade',
            'monthly_income' => 'rendimento mensal', 'employment_type' => 'situação profissional',
            'guarantors.*.full_name' => 'nome do fiador', 'guarantors.*.document_number' => 'documento do fiador',
            'guarantors.*.phone' => 'telefone do fiador', 'collaterals.*.description' => 'descrição da garantia',
            'collaterals.*.estimated_value' => 'valor da garantia', 'new_documents.*.file' => 'documento',
        ];
    }

    public function messages(): array
    {
        return [
            'declaration.accepted' => 'Tem de confirmar a declaração de veracidade.',
            'consent_data_processing.accepted' => 'Tem de autorizar o tratamento dos dados pessoais.',
            'consent_credit_check.accepted' => 'Tem de autorizar a análise de crédito.',
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();
        $data['new_documents'] = collect($this->file('new_documents', []))
            ->map(fn ($doc, $i) => ['type' => $this->input("new_documents.{$i}.type", 'other'), 'file' => $doc['file'] ?? null])
            ->filter(fn ($d) => $d['file'])->values()->all();

        return $data;
    }
}
