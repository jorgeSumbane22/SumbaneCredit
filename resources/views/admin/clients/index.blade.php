@extends('layouts.admin')
@section('title', 'Clientes')

@section('content')
<x-page-header title="Clientes" :subtitle="$clients->total().' cliente(s) encontrados'">
    @can('clients.export')
        <a href="{{ route('admin.clients.export', request()->query() + ['todos' => 1]) }}" class="btn btn-outline-success" data-bs-toggle="tooltip" title="Exportação registada na auditoria"><i class="bi bi-file-earmark-excel me-1"></i>Exportar</a>
    @endcan
    @can('clients.create')
        <a href="{{ route('admin.clients.create') }}" class="btn btn-success"><i class="bi bi-person-plus me-1"></i>Novo cliente</a>
    @endcan
</x-page-header>

<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-4"><x-input name="q" label="Pesquisar" :value="request('q')" placeholder="Nome, n.º, documento, NUIT, telefone" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="estado" label="Estado" :options="\App\Models\Client::STATUSES" :value="request('estado')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="kyc" label="Identificação" :options="\App\Models\Client::KYC_STATUSES" :value="request('kyc')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="provincia" label="Província" :options="array_combine(\App\Models\Client::PROVINCES, \App\Models\Client::PROVINCES)" :value="request('provincia')" placeholder="Todas" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="situacao" label="Situação" :options="['com-emprestimo' => 'Com empréstimo activo', 'em-atraso' => 'Em atraso']" :value="request('situacao')" placeholder="Todas" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading><i class="bi bi-search me-1"></i>Filtrar</button> <a href="{{ route('admin.clients.index') }}" class="btn btn-link">Limpar</a></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>N.º</th><th>Nome</th><th>Documento</th><th>Contacto</th><th>Província</th><th>Emp. activos</th><th>Identificação</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse ($clients as $c)
                <tr>
                    <td class="small fw-semibold">{{ $c->client_number }}</td>
                    <td><a href="{{ route('admin.clients.show', $c) }}" class="fw-semibold">{{ $c->full_name }}</a>@if ($c->is_demo) <span class="demo-badge">demo</span>@endif</td>
                    <td class="small">{{ strtoupper($c->document_type) }} {{ $c->document_number }}</td>
                    <td class="small">{{ $c->phone }}<div class="text-muted">{{ $c->email }}</div></td>
                    <td class="small">{{ $c->address?->province ?? '—' }}</td>
                    <td>{{ $c->active_loans_count }}</td>
                    <td><x-badge :label="\App\Models\Client::KYC_STATUSES[$c->kyc_status]" :color="['pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'][$c->kyc_status]" /></td>
                    <td><x-badge :label="$c->statusLabel()" :color="['active' => 'success', 'inactive' => 'secondary', 'blocked' => 'danger'][$c->status]" /></td>
                    <td class="text-end"><a href="{{ route('admin.clients.show', $c) }}" class="btn btn-sm btn-outline-primary">Abrir</a></td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty-state icon="people" title="Nenhum cliente encontrado" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $clients->links() }}</div>
@endsection
