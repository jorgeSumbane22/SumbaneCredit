@extends('layouts.admin')
@section('title', 'Empréstimos')

@section('content')
<x-page-header title="Empréstimos" :subtitle="$loans->total().' empréstimo(s)'">
    @can('reports.export')<a href="{{ route('admin.reports.excel', ['type' => 'loans', 'todos' => 1] + request()->only('estado')) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Exportar</a>@endcan
</x-page-header>

<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-4"><x-input name="q" label="Pesquisar" :value="request('q')" placeholder="N.º empréstimo/contrato, cliente" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="estado" label="Estado" :options="\App\Enums\LoanStatus::options()" :value="request('estado')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="produto" label="Produto" :options="$products->all()" :value="request('produto')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="atraso" label="Atraso" :options="['sim' => 'Em atraso', '30' => '+30 dias', '90' => '+90 dias']" :value="request('atraso')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
</form>

<div class="card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>N.º</th><th>Cliente</th><th>Produto</th><th class="num">Capital</th><th class="num">Em dívida</th><th>Próx. venc.</th><th>Atraso</th><th>Agente</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse ($loans as $l)
            <tr>
                <td><a href="{{ route('admin.loans.show', $l) }}" class="fw-semibold">{{ $l->loan_number }}</a><div class="small text-muted">{{ $l->contract_number }}</div></td>
                <td>{{ $l->client->full_name }}</td>
                <td class="small">{{ $l->product->name }}</td>
                <td class="num">@money($l->principal_amount)</td>
                <td class="num">@money($l->total_outstanding)</td>
                <td class="small">@date($l->next_due_date)</td>
                <td>@if ($l->days_past_due > 0)<span class="badge badge-soft-danger">{{ $l->days_past_due }} dias</span>@else<span class="text-muted small">—</span>@endif</td>
                <td class="small">{{ $l->officer?->name ?? '—' }}</td>
                <td><x-badge :status="$l->status" /></td>
            </tr>
        @empty
            <tr><td colspan="9"><x-empty-state icon="briefcase" title="Sem empréstimos" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $loans->links() }}</div>
@endsection
