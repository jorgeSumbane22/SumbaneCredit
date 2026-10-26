@extends('layouts.admin')
@section('title', 'Pagamentos')

@section('content')
<x-page-header title="Pagamentos" :subtitle="$pendingCount.' pagamento(s) por confirmar'">
    @can('payments.reconcile')<a href="{{ route('admin.reconciliation.index') }}" class="btn btn-outline-primary"><i class="bi bi-arrow-left-right me-1"></i>Reconciliação</a>@endcan
    @can('payments.register')<a href="{{ route('admin.payments.create') }}" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Registar pagamento</a>@endcan
</x-page-header>

<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-3"><x-input name="q" label="Pesquisar" :value="request('q')" placeholder="N.º, recibo, referência, cliente" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="estado" label="Estado" :options="\App\Enums\PaymentStatus::options()" :value="request('estado')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="metodo" label="Método" :options="\App\Enums\PaymentMethod::options()" :value="request('metodo')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-4 col-md-1"><x-select name="origem" label="Origem" :options="['staff' => 'Balcão', 'client_portal' => 'Portal']" :value="request('origem')" placeholder="Todas" col="mb-0" /></div>
    <div class="col-sm-6 col-md-1"><x-input name="de" type="date" label="De" :value="request('de')" col="mb-0" /></div>
    <div class="col-sm-6 col-md-1"><x-input name="ate" type="date" label="Até" :value="request('ate')" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
    @can('reports.export')<div class="col-auto"><a class="btn btn-outline-success" href="{{ route('admin.reports.excel', ['type' => 'payments', 'periodo' => 'custom', 'de' => request('de', today()->startOfMonth()->toDateString()), 'ate' => request('ate', today()->toDateString())]) }}"><i class="bi bi-file-earmark-excel"></i></a></div>@endcan
</form>

<div class="card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>N.º</th><th>Data</th><th>Cliente</th><th>Empréstimo</th><th>Método / Ref.</th><th class="num">Valor</th><th>Origem</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse ($payments as $p)
            <tr>
                <td><a href="{{ route('admin.payments.show', $p) }}" class="fw-semibold">{{ $p->payment_number }}</a>@if ($p->type === 'settlement')<div><span class="badge badge-soft-info">Liquidação</span></div>@endif</td>
                <td>@date($p->paid_at)</td><td>{{ $p->client->full_name }}</td><td>{{ $p->loan->loan_number }}</td>
                <td class="small">{{ $p->method->label() }}<div class="text-muted">{{ $p->reference }}</div></td>
                <td class="num fw-semibold">@money($p->amount)</td>
                <td class="small">{{ $p->sourceLabel() }}</td>
                <td><x-badge :status="$p->status" /></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty-state icon="cash-stack" title="Sem pagamentos" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $payments->links() }}</div>
@endsection
