@extends('layouts.admin')
@section('title', 'Suporte')

@section('content')
<x-page-header title="Suporte ao cliente" subtitle="Tickets abertos pelos clientes no portal." />
<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-4"><x-input name="q" label="Pesquisar" :value="request('q')" placeholder="N.º, assunto ou cliente" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="estado" label="Estado" :options="['abertos' => 'Por resolver', 'todos' => 'Todos'] + \App\Models\SupportTicket::STATUSES" :value="request('estado', 'abertos')" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="categoria" label="Categoria" :options="\App\Models\SupportTicket::CATEGORIES" :value="request('categoria')" placeholder="Todas" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="responsavel" label="Responsável" :options="['eu' => 'Os meus']" :value="request('responsavel')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
</form>
<div class="card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>N.º</th><th>Assunto</th><th>Cliente</th><th>Categoria</th><th>Prioridade</th><th>Responsável</th><th>Última actividade</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse ($tickets as $t)
            <tr>
                <td><a href="{{ route('admin.tickets.show', $t) }}" class="fw-semibold">{{ $t->ticket_number }}</a></td>
                <td>{{ \Illuminate\Support\Str::limit($t->subject, 50) }}</td><td>{{ $t->client->full_name }}</td><td class="small">{{ $t->categoryLabel() }}</td>
                <td><span class="badge badge-soft-{{ ['urgent' => 'danger', 'high' => 'warning', 'normal' => 'secondary', 'low' => 'secondary'][$t->priority] }}">{{ $t->priorityLabel() }}</span></td>
                <td class="small">{{ $t->assignee?->name ?? '—' }}</td><td class="small">{{ $t->last_reply_at?->diffForHumans() }}</td>
                <td><x-badge :label="$t->statusLabel()" :color="$t->statusColor()" /></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty-state icon="headset" title="Sem tickets" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $tickets->links() }}</div>
@endsection
