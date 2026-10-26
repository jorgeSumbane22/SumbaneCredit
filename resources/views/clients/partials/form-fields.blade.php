{{-- Campos da ficha do cliente (portal e painel). $client pode ser null; $lockIdentity bloqueia identificação. --}}
@php
    $lock = $lockIdentity ?? false;
    $emp = $client?->employment;
    $addr = $client?->address;
@endphp
<div class="form-section-title mt-0">Identificação</div>
@if ($lock)
    <div class="alert alert-light border small"><i class="bi bi-lock me-1"></i>A sua identificação foi verificada. Para alterar nome, documento ou data de nascimento, contacte a instituição.</div>
@endif
<div class="row">
    <x-input name="full_name" label="Nome completo" :value="$client?->full_name" required col="col-md-6 mb-3" :disabled="$lock" />
    <x-input name="birth_date" type="date" label="Data de nascimento" :value="$client?->birth_date?->toDateString()" required col="col-md-3 mb-3" :disabled="$lock" />
    <x-input name="nationality" label="Nacionalidade" :value="$client?->nationality ?? 'Moçambicana'" col="col-md-3 mb-3" />
    <x-select name="document_type" label="Tipo de documento" :options="\App\Models\Client::DOCUMENT_TYPES" :value="$client?->document_type ?? 'bi'" required col="col-md-3 mb-3" :disabled="$lock" />
    <x-input name="document_number" label="N.º do documento" :value="$client?->document_number" required col="col-md-3 mb-3" :disabled="$lock" />
    <x-input name="document_expiry" type="date" label="Validade do documento" :value="$client?->document_expiry?->toDateString()" col="col-md-3 mb-3" />
    <x-input name="nuit" label="NUIT" :value="$client?->nuit" col="col-md-3 mb-3" maxlength="9" inputmode="numeric" :disabled="$lock && $client?->nuit" />
</div>

<div class="form-section-title">Contactos</div>
<div class="row">
    <x-input name="phone" label="Telemóvel" :value="$client?->phone" required col="col-md-4 mb-3" placeholder="841234567" />
    <x-input name="phone_alt" label="Telefone alternativo" :value="$client?->phone_alt" col="col-md-4 mb-3" />
    <x-input name="email" type="email" label="Email" :value="$client?->email" col="col-md-4 mb-3" :readonly="! auth()->user()->isStaff() && $client?->user_id" />
</div>

<div class="form-section-title">Morada</div>
<div class="row">
    <x-select name="province" label="Província" :options="array_combine(\App\Models\Client::PROVINCES, \App\Models\Client::PROVINCES)" :value="$addr?->province" placeholder="Seleccione" required col="col-md-4 mb-3" />
    <x-input name="district" label="Distrito" :value="$addr?->district" col="col-md-4 mb-3" />
    <x-input name="locality" label="Localidade / Posto administrativo" :value="$addr?->locality" col="col-md-4 mb-3" />
    <x-input name="neighborhood" label="Bairro" :value="$addr?->neighborhood" col="col-md-4 mb-3" />
    <x-input name="address_line" label="Rua / Avenida, n.º, quarteirão" :value="$addr?->address_line" col="col-md-8 mb-3" />
</div>

<div class="form-section-title">Situação profissional e rendimentos</div>
<div class="row">
    <x-select name="employment[employment_type]" label="Situação profissional" :options="\App\Models\Client::EMPLOYMENT_TYPES" :value="$emp?->employment_type" placeholder="Seleccione" col="col-md-4 mb-3" />
    <x-input name="occupation" label="Profissão / actividade" :value="$client?->occupation" col="col-md-4 mb-3" />
    <x-input name="employment[position]" label="Cargo" :value="$emp?->position" col="col-md-4 mb-3" />
    <x-input name="employment[employer_name]" label="Entidade empregadora / negócio" :value="$emp?->employer_name" col="col-md-4 mb-3" />
    <x-input name="employment[employer_phone]" label="Telefone da entidade" :value="$emp?->employer_phone" col="col-md-4 mb-3" />
    <x-input name="employment[start_date]" type="date" label="Início de funções / actividade" :value="$emp?->start_date?->toDateString()" col="col-md-4 mb-3" />
    <x-input name="employment[employer_address]" label="Endereço da entidade" :value="$emp?->employer_address" col="col-md-12 mb-3" />
    <x-input name="employment[monthly_income]" type="number" step="0.01" min="0" label="Rendimento mensal líquido (MZN)" :value="$emp?->monthly_income" col="col-md-4 mb-3" />
    <x-input name="employment[other_income]" type="number" step="0.01" min="0" label="Outros rendimentos mensais (MZN)" :value="$emp?->other_income" col="col-md-4 mb-3" />
    <x-input name="employment[monthly_expenses]" type="number" step="0.01" min="0" label="Despesas mensais (MZN)" :value="$emp?->monthly_expenses" col="col-md-4 mb-3" />
    <input type="hidden" name="monthly_income" value="{{ old('monthly_income', $client?->monthly_income) }}">
</div>

<div class="form-section-title">Contacto de emergência</div>
<div class="row">
    <x-input name="emergency_contact_name" label="Nome" :value="$client?->emergency_contact_name" col="col-md-5 mb-3" />
    <x-input name="emergency_contact_phone" label="Telefone" :value="$client?->emergency_contact_phone" col="col-md-4 mb-3" />
    <x-input name="emergency_contact_relationship" label="Relação" :value="$client?->emergency_contact_relationship" col="col-md-3 mb-3" placeholder="Ex.: cônjuge, irmão" />
</div>
