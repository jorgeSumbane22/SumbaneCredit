<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Dados do cliente (portal e painel administrativo). */
class ClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $employment = (array) $this->input('employment', []);
        foreach (['monthly_income', 'other_income', 'monthly_expenses'] as $k) {
            if (isset($employment[$k]) && $employment[$k] !== '') {
                $employment[$k] = Money::parseInput($employment[$k]) ?? $employment[$k];
            }
        }
        $this->merge([
            'phone' => preg_replace('/[\s\-]/', '', (string) $this->input('phone')),
            'document_number' => strtoupper(trim((string) $this->input('document_number'))),
            'monthly_income' => Money::parseInput($this->input('monthly_income')) ?? $this->input('monthly_income'),
            'employment' => $employment,
        ]);
    }

    public function rules(): array
    {
        $client = $this->route('client') ?? $this->user()?->client;
        $clientId = $client?->id;
        $isStaff = $this->user()?->isStaff();

        return [
            'full_name' => [$isStaff ? 'required' : 'sometimes', 'string', 'min:5', 'max:150'],
            'document_type' => [$isStaff ? 'required' : 'sometimes', Rule::in(array_keys(Client::DOCUMENT_TYPES))],
            'document_number' => [$isStaff ? 'required' : 'sometimes', 'string', 'max:50',
                Rule::unique('clients', 'document_number')->where('document_type', $this->input('document_type', $client?->document_type))->ignore($clientId)],
            'document_expiry' => ['nullable', 'date'],
            'nuit' => ['nullable', 'digits:9', Rule::unique('clients', 'nuit')->ignore($clientId)],
            'birth_date' => [$isStaff ? 'required' : 'sometimes', 'date', 'before:-18 years'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'phone' => ['required', 'regex:/^(\+?258)?8[2-7]\d{7}$/'],
            'phone_alt' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'monthly_income' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],
            'province' => ['required', Rule::in(Client::PROVINCES)],
            'district' => ['nullable', 'string', 'max:100'],
            'locality' => ['nullable', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'string', 'max:100'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'employment.employment_type' => ['nullable', Rule::in(array_keys(Client::EMPLOYMENT_TYPES))],
            'employment.employer_name' => ['nullable', 'string', 'max:150'],
            'employment.position' => ['nullable', 'string', 'max:150'],
            'employment.employer_phone' => ['nullable', 'string', 'max:30'],
            'employment.employer_address' => ['nullable', 'string', 'max:255'],
            'employment.start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'employment.monthly_income' => ['nullable', 'numeric', 'min:0'],
            'employment.other_income' => ['nullable', 'numeric', 'min:0'],
            'employment.monthly_expenses' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Indique um número de telemóvel moçambicano válido (ex.: 841234567).',
            'nuit.digits' => 'O NUIT deve ter 9 dígitos.',
            'birth_date.before' => 'O cliente deve ter pelo menos 18 anos.',
            'document_number.unique' => 'Já existe um cliente com este documento.',
        ];
    }
}
