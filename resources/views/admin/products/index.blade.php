@extends('layouts.admin')
@section('title', 'Produtos de crédito')

@section('content')
<x-page-header title="Produtos de crédito" subtitle="Condições versionadas: alterações não afectam contratos existentes.">
    @can('products.manage')<a href="{{ route('admin.products.create') }}" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Novo produto</a>@endcan
</x-page-header>

<div class="card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Código</th><th>Produto</th><th>Montantes</th><th>Prazo</th><th>Taxa</th><th>Versão</th><th>Pedidos</th><th>Empréstimos</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($products as $p)
            @php($v = $p->currentVersion)
            <tr>
                <td class="small fw-semibold">{{ $p->code }}</td>
                <td><a href="{{ route('admin.products.show', $p) }}" class="fw-semibold">{{ $p->name }}</a> @unless ($p->is_public)<span class="badge badge-soft-secondary">interno</span>@endunless</td>
                <td class="small">@if ($v) @money($v->min_amount) – @money($v->max_amount) @endif</td>
                <td class="small">{{ $v?->min_term }}–{{ $v?->max_term }}</td>
                <td class="small">{{ $v?->rateLabel() }}</td>
                <td>v{{ $v?->version }} <span class="small text-muted">({{ $p->versions_count }})</span></td>
                <td>{{ $p->applications_count }}</td><td>{{ $p->loans_count }}</td>
                <td><x-badge :label="$p->status === 'active' ? 'Activo' : 'Inactivo'" :color="$p->status === 'active' ? 'success' : 'secondary'" /></td>
                <td class="text-end"><a href="{{ route('admin.products.show', $p) }}" class="btn btn-sm btn-outline-primary">Abrir</a></td>
            </tr>
        @empty
            <tr><td colspan="10"><x-empty-state icon="box-seam" title="Sem produtos" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
@endsection
