@extends('layouts.admin')
@section('title', $product ? 'Editar produto' : 'Novo produto')

@php
    $v = $version;
    $fees = old('fees', $v?->fees ?? []);
    $freqs = old('allowed_frequencies', $v?->allowed_frequencies ?? ['monthly']);
    $methods = old('amortization_methods', $v?->amortization_methods ?? ['annuity']);
    $el = $v?->eligibility_rules ?? [];
@endphp

@section('content')
<x-page-header :title="$product ? 'Editar '.$product->name : 'Novo produto de crédito'" :breadcrumbs="['Produtos' => route('admin.products.index'), ($product ? $product->code : 'Novo') => null]" />

@if ($product)
    <div class="alert alert-warning small"><i class="bi bi-layers me-1"></i>Se alterar alguma condição financeira, é criada a <strong>versão {{ ($v?->version ?? 0) + 1 }}</strong>. Pedidos e contratos já registados mantêm a versão anterior.</div>
@endif

<form method="POST" action="{{ $product ? route('admin.products.update', $product) : route('admin.products.store') }}">
    @csrf
    @if ($product) @method('PUT') @endif
    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card mb-4"><div class="card-header"><h2 class="card-title">Identificação e apresentação</h2></div><div class="card-body">
                <div class="row">
                    <x-input name="code" label="Código" :value="$product?->code" required col="col-md-4 mb-3" maxlength="30" />
                    <x-input name="name" label="Nome" :value="$product?->name" required col="col-md-8 mb-3" />
                </div>
                <x-textarea name="description" label="Descrição" :value="$product?->description" rows="3" required />
                <x-textarea name="requirements" label="Requisitos" :value="$product?->requirements" rows="3" />
                <x-textarea name="contract_conditions" label="Condições contratuais específicas" :value="$product?->contract_conditions" rows="4" help="Texto incluído no contrato. Deve ser validado juridicamente pela instituição." />
                <div class="row">
                    <x-select name="status" label="Estado" :options="['active' => 'Activo', 'inactive' => 'Inactivo']" :value="$product?->status ?? 'active'" col="col-md-6 mb-3" />
                    <div class="col-md-6 mb-3 pt-4"><div class="form-check"><input type="hidden" name="is_public" value="0"><input class="form-check-input" type="checkbox" name="is_public" id="is_public" value="1" @checked(old('is_public', $product?->is_public ?? true))><label class="form-check-label" for="is_public">Publicar no website</label></div></div>
                </div>
            </div></div>

            <div class="card mb-4"><div class="card-header"><h2 class="card-title">Elegibilidade</h2></div><div class="card-body">
                <div class="row">
                    <x-input name="eligibility[min_monthly_income]" type="number" step="0.01" min="0" label="Rendimento mínimo (MZN)" :value="$el['min_monthly_income'] ?? null" col="col-md-4 mb-3" />
                    <x-input name="eligibility[max_active_loans]" type="number" min="1" label="Máx. empréstimos activos" :value="$el['max_active_loans'] ?? null" col="col-md-4 mb-3" />
                    <x-input name="eligibility[min_age]" type="number" min="18" label="Idade mínima" :value="$el['min_age'] ?? 18" col="col-md-4 mb-3" />
                </div>
                <div class="form-check"><input type="hidden" name="eligibility[requires_guarantor]" value="0"><input class="form-check-input" type="checkbox" name="eligibility[requires_guarantor]" value="1" id="rg" @checked(old('eligibility.requires_guarantor', $el['requires_guarantor'] ?? false))><label class="form-check-label" for="rg">Exige fiador</label></div>
                <div class="form-check"><input type="hidden" name="eligibility[requires_collateral]" value="0"><input class="form-check-input" type="checkbox" name="eligibility[requires_collateral]" value="1" id="rc" @checked(old('eligibility.requires_collateral', $el['requires_collateral'] ?? false))><label class="form-check-label" for="rc">Exige garantia</label></div>
            </div></div>
        </div>

        <div class="col-xl-6">
            <div class="card mb-4"><div class="card-header"><h2 class="card-title">Condições financeiras</h2></div><div class="card-body">
                <div class="row">
                    <x-input name="min_amount" type="number" step="0.01" label="Montante mínimo (MZN)" :value="$v?->min_amount" required col="col-md-6 mb-3" />
                    <x-input name="max_amount" type="number" step="0.01" label="Montante máximo (MZN)" :value="$v?->max_amount" required col="col-md-6 mb-3" />
                    <x-input name="min_term" type="number" label="Prestações (mín.)" :value="$v?->min_term" required col="col-md-6 mb-3" />
                    <x-input name="max_term" type="number" label="Prestações (máx.)" :value="$v?->max_term" required col="col-md-6 mb-3" />
                    <x-input name="interest_rate" type="number" step="0.0001" min="0" label="Taxa de juro nominal (%)" :value="$v?->interest_rate" required col="col-md-6 mb-3" />
                    <x-select name="rate_period" label="Periodicidade da taxa" :options="['monthly' => 'Mensal', 'annual' => 'Anual']" :value="$v?->rate_period ?? 'monthly'" col="col-md-6 mb-3" />
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Periodicidades de pagamento</label>
                        @foreach (\App\Enums\RepaymentFrequency::cases() as $f)
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="allowed_frequencies[]" value="{{ $f->value }}" id="fq{{ $f->value }}" @checked(in_array($f->value, $freqs, true))><label class="form-check-label" for="fq{{ $f->value }}">{{ $f->label() }}</label></div>
                        @endforeach
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Métodos de amortização</label>
                        @foreach (\App\Enums\AmortizationMethod::cases() as $m)
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="amortization_methods[]" value="{{ $m->value }}" id="am{{ $m->value }}" @checked(in_array($m->value, $methods, true))><label class="form-check-label small" for="am{{ $m->value }}">{{ $m->label() }}</label></div>
                        @endforeach
                    </div>
                </div>
                <div class="form-section-title">Encargos</div>
                <div data-repeater data-max="6">
                    <div data-repeater-list>
                        @foreach ($fees as $i => $fee)@include('admin.products.partials.fee-row', ['i' => $i, 'fee' => $fee])@endforeach
                    </div>
                    <template>@include('admin.products.partials.fee-row', ['i' => '__INDEX__', 'fee' => []])</template>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-repeater-add><i class="bi bi-plus"></i> Adicionar encargo</button>
                </div>
                <div class="form-section-title">Atrasos e liquidação</div>
                <div class="row">
                    <x-select name="penalty_type" label="Penalização por atraso" :options="['none' => 'Sem penalização', 'fixed' => 'Valor fixo (MZN)', 'percent' => '% do valor em atraso']" :value="$v?->penalty_type ?? 'none'" col="col-md-4 mb-3" />
                    <x-input name="penalty_value" type="number" step="0.0001" min="0" label="Valor" :value="$v?->penalty_value" col="col-md-4 mb-3" />
                    <x-input name="penalty_grace_days" type="number" min="0" label="Dias de carência" :value="$v?->penalty_grace_days ?? 0" col="col-md-4 mb-3" />
                </div>
                <x-select name="early_settlement_rule" label="Liquidação antecipada" :options="['waive_future_interest' => 'Dispensa juros dos períodos futuros', 'full_contractual' => 'Juros contratados devidos na totalidade']" :value="$v?->early_settlement_rule ?? 'waive_future_interest'" />
                <p class="small text-muted">O sistema não impõe limites legais a taxas ou penalizações; os valores devem ser validados pela instituição face à regulamentação aplicável.</p>
                @if ($product)<x-textarea name="change_notes" label="Notas da alteração (registadas na nova versão)" rows="2" />@endif
            </div></div>
        </div>
    </div>
    <button class="btn btn-success" type="submit"><i class="bi bi-check2 me-1"></i>Guardar produto</button>
    <a href="{{ $product ? route('admin.products.show', $product) : route('admin.products.index') }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
