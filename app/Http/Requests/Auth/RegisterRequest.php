<?php

namespace App\Http\Requests\Auth;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:5', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^(\+?258)?\s?8[2-7]\d{7}$/'],
            'document_type' => ['required', Rule::in(array_keys(Client::DOCUMENT_TYPES))],
            'document_number' => ['required', 'string', 'max:50', Rule::unique('clients', 'document_number')->where('document_type', $this->input('document_type'))],
            'birth_date' => ['required', 'date', 'before:-18 years'],
            'province' => ['required', Rule::in(Client::PROVINCES)],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
            'terms' => ['accepted'],
            'privacy' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Indique um número de telemóvel moçambicano válido (ex.: 84 123 4567).',
            'birth_date.before' => 'É necessário ter pelo menos 18 anos.',
            'document_number.unique' => 'Já existe um cliente registado com este documento.',
            'terms.accepted' => 'Tem de aceitar os termos e condições.',
            'privacy.accepted' => 'Tem de aceitar a política de privacidade.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/[\s\-]/', '', (string) $this->input('phone')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'document_number' => strtoupper(trim((string) $this->input('document_number'))),
        ]);
    }
}
