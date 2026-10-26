@extends('layouts.admin')
@section('title', $product->name)

@section('content')
<x-page-header :title="$product->name" :subtitle="$product->code.' · versão em vigor v'.$product->currentVersion?->version" :breadcrumbs="['Produtos' => route('admin.products.index'), $product->code => null]">
    <x-badge :label="$product->status === 'active' ? 'Activo' : 'Inactivo'" :color="$product->status === 'active' ? 'success' : 'secondary'" class="fs-6" />
    @can('products.manage')
        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
        <x-action-form :action="route('admin.products.toggle', $product)" :confirm="$product->status === 'active' ? 'Desactivar o produto? Deixa de aceitar novos pedidos.' : 'Activar o produto?'" :button="$product->status === 'active' ? 'Desactivar' : 'Activar'" class="btn btn-outline-secondary" />
    @endcan
</x-page-header>

<div class="row g-4">
    <div class="col-lg-6">
        @if ($v = $product->currentVersion)
        <div class="card mb-4"><div class="card-header"><h2 class="card-title">Condições em vigor (v{{ $v->version }}, desde @date($v->effective_from))</h2></div><div class="card-body">
            <dl class="dl-grid small">
                <dt>Montante</dt><dd>@money($v->min_amount) a @money($v->max_amount)</dd>
                <dt>Prestações</dt><dd>{{ $v->min_term }} a {{ $v->max_term }}</dd>
                <dt>Taxa</dt><dd>{{ $v->rateLabel() }}</dd>
                <dt>Periodicidades</dt><dd>{{ collect($v->frequencies())->map->label()->implode(', ') }}</dd>
                <dt>Métodos</dt><dd>{{ collect($v->methods())->map->label()->implode('; ') }}</dd>
                <dt>Encargos</dt><dd>{{ $v->feesLabel() }}</dd>
                <dt>Penalizações</dt><dd>{{ $v->penaltyLabel() }}</dd>
                <dt>Liquidação antecipada</dt><dd>{{ $v->earlySettlementLabel() }}</dd>
                <dt>Elegibilidade</dt><dd>
                    Idade mín. {{ $v->eligibility('min_age', 18) }} anos
                    @if ($v->eligibility('min_monthly_income')) · rendimento ≥ @money($v->eligibility('min_monthly_income'))@endif
                    @if ($v->eligibility('max_active_loans')) · máx. {{ $v->eligibility('max_active_loans') }} empréstimo(s) activo(s)@endif
                    @if ($v->eligibility('requires_guarantor')) · exige fiador @endif
                    @if ($v->eligibility('requires_collateral')) · exige garantia @endif
                </dd>
            </dl>
        </div></div>
        @endif
        <div class="card"><div class="card-header"><h2 class="card-title">Histórico de versões</h2></div>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>Versão</th><th>Desde</th><th>Taxa</th><th>Montantes</th><th>Utilização</th><th>Notas</th></tr></thead>
                <tbody>
                @foreach ($product->versions as $ver)
                    <tr class="{{ $ver->id === $product->current_version_id ? 'table-success' : '' }}">
                        <td>v{{ $ver->version }}</td><td class="small">@datetime($ver->effective_from)</td><td class="small">{{ $ver->rateLabel() }}</td>
                        <td class="small">@money($ver->min_amount) – @money($ver->max_amount)</td>
                        <td class="small">{{ $usage[$ver->id]['applications'] }} pedidos · {{ $usage[$ver->id]['loans'] }} contratos</td>
                        <td class="small">{{ $ver->change_notes }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header"><h2 class="card-title">Simulação interna</h2></div><div class="card-body">
            @if ($product->currentVersion)
                @include('public.partials.simulator', ['products' => collect([$product]), 'url' => route('admin.products.simulate'), 'compact' => true])
            @endif
        </div></div>
    </div>
</div>
@endsection
