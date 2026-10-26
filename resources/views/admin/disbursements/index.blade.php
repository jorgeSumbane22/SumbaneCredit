@extends('layouts.admin')
@section('title', 'Desembolsos')

@section('content')
<x-page-header title="Desembolsos" subtitle="Registo → autorização (outro utilizador) → confirmação da execução." />

@if ($ready->isNotEmpty())
    <div class="card mb-4 border-success"><div class="card-header"><h2 class="card-title"><i class="bi bi-hourglass me-1"></i>Contratos formalizados a aguardar registo de desembolso ({{ $ready->count() }})</h2></div>
        <div class="list-group list-group-flush">
            @foreach ($ready as $l)
                <a href="{{ route('admin.loans.show', $l) }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span><strong>{{ $l->loan_number }}</strong> — {{ $l->client->full_name }}</span><span>@money($l->principal_amount)</span></a>
            @endforeach
        </div>
    </div>
@endif

<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-4"><x-input name="q" label="Pesquisar" :value="request('q')" placeholder="N.º, referência, empréstimo, cliente" col="mb-0" /></div>
    <div class="col-sm-6 col-md-3"><x-select name="estado" label="Estado" :options="\App\Enums\DisbursementStatus::options()" :value="request('estado')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-6 col-md-3"><x-select name="metodo" label="Método" :options="\App\Enums\PaymentMethod::options()" :value="request('metodo')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
</form>

<div class="card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>N.º</th><th>Empréstimo</th><th>Cliente</th><th class="num">Líquido</th><th>Método</th><th>Registado</th><th>Autorizado</th><th>Data</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse ($items as $d)
            <tr>
                <td><a href="{{ route('admin.disbursements.show', $d) }}" class="fw-semibold">{{ $d->disbursement_number }}</a></td>
                <td>{{ $d->loan->loan_number }}</td><td>{{ $d->loan->client->full_name }}</td>
                <td class="num">@money($d->net_amount)</td><td class="small">{{ $d->method->label() }}</td>
                <td class="small">{{ $d->requester?->name }}</td><td class="small">{{ $d->authorizer?->name ?? '—' }}</td>
                <td class="small">@date($d->disbursed_at)</td><td><x-badge :status="$d->status" /></td>
            </tr>
        @empty
            <tr><td colspan="9"><x-empty-state icon="send" title="Sem desembolsos" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $items->links() }}</div>
@endsection
