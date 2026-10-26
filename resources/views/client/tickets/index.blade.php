@extends('layouts.client')
@section('title', 'Suporte')

@section('content')
<x-page-header title="Suporte" subtitle="Pedidos de ajuda, esclarecimentos e reclamações." :breadcrumbs="['Início' => route('client.dashboard'), 'Suporte' => null]">
    <a href="{{ route('client.tickets.create') }}" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Novo pedido</a>
</x-page-header>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>N.º</th><th>Assunto</th><th>Categoria</th><th>Prioridade</th><th>Última actividade</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse ($tickets as $t)
                <tr>
                    <td><a href="{{ route('client.tickets.show', $t) }}" class="fw-semibold">{{ $t->ticket_number }}</a></td>
                    <td>{{ $t->subject }}</td><td>{{ $t->categoryLabel() }}</td><td>{{ $t->priorityLabel() }}</td>
                    <td class="small">{{ $t->last_reply_at?->diffForHumans() }}</td>
                    <td><x-badge :label="$t->statusLabel()" :color="$t->statusColor()" /></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state icon="headset" title="Sem pedidos de suporte" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $tickets->links() }}</div>
@endsection
