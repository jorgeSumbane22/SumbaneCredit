@extends('layouts.admin')
@section('title', 'Pedidos de crédito')

@section('content')
<x-page-header title="Pedidos de crédito" :subtitle="$applications->total().' pedido(s)'" />

<div class="d-flex flex-wrap gap-2 mb-3">
    @php($open = collect(\App\Enums\ApplicationStatus::openValues())->sum(fn ($s) => $counts[$s] ?? 0))
    <a href="{{ route('admin.applications.index', ['estado' => 'abertos']) }}" class="btn btn-sm {{ request('estado') === 'abertos' ? 'btn-primary' : 'btn-outline-primary' }}">Em aberto <span class="badge text-bg-light">{{ $open }}</span></a>
    @foreach (\App\Enums\ApplicationStatus::cases() as $s)
        <a href="{{ route('admin.applications.index', ['estado' => $s->value]) }}" class="btn btn-sm {{ request('estado') === $s->value ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $s->label() }} <span class="badge text-bg-light">{{ $counts[$s->value] ?? 0 }}</span></a>
    @endforeach
    <a href="{{ route('admin.applications.index') }}" class="btn btn-sm btn-link">Todos</a>
</div>

<form class="filter-bar row g-2 align-items-end" method="GET">
    <input type="hidden" name="estado" value="{{ request('estado') }}">
    <div class="col-md-4"><x-input name="q" label="Pesquisar" :value="request('q')" placeholder="N.º do pedido, cliente, documento" col="mb-0" /></div>
    <div class="col-sm-6 col-md-2"><x-select name="produto" label="Produto" :options="$products->all()" :value="request('produto')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-6 col-md-2"><x-select name="responsavel" label="Responsável" :options="['eu' => 'Atribuídos a mim']" :value="request('responsavel')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-6 col-md-1"><x-input name="de" type="date" label="De" :value="request('de')" col="mb-0" /></div>
    <div class="col-sm-6 col-md-1"><x-input name="ate" type="date" label="Até" :value="request('ate')" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
    @can('reports.export')<div class="col-auto"><a class="btn btn-outline-success" href="{{ route('admin.reports.excel', ['type' => 'applications', 'periodo' => 'last_12_months']) }}"><i class="bi bi-file-earmark-excel"></i></a></div>@endcan
</form>

<div class="card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>N.º</th><th>Cliente</th><th>Produto</th><th class="num">Montante</th><th>Prestações</th><th>Avaliação</th><th>Responsável</th><th>Submetido</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse ($applications as $a)
            <tr>
                <td><a href="{{ route('admin.applications.show', $a) }}" class="fw-semibold">{{ $a->application_number }}</a></td>
                <td>{{ $a->client->full_name }}<div class="small text-muted">{{ $a->client->client_number }}</div></td>
                <td class="small">{{ $a->product->name }}</td>
                <td class="num">@money($a->requested_amount)</td>
                <td>{{ $a->term }}</td>
                <td>@if ($a->latestAssessment)<x-badge :label="$a->latestAssessment->resultLabel()" :color="\App\Models\CreditAssessment::colorFor($a->latestAssessment->result)" />@else<span class="small text-muted">—</span>@endif</td>
                <td class="small">{{ $a->assignee?->name ?? '—' }}</td>
                <td class="small">@datetime($a->submitted_at)</td>
                <td><x-badge :status="$a->status" /></td>
            </tr>
        @empty
            <tr><td colspan="9"><x-empty-state icon="file-earmark-text" title="Sem pedidos" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $applications->links() }}</div>
@endsection
