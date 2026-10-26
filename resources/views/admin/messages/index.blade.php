@extends('layouts.admin')
@section('title', 'Mensagens de contacto')

@section('content')
<x-page-header title="Mensagens de contacto" subtitle="Mensagens recebidas pelo formulário do website." />
<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-4"><x-input name="q" label="Pesquisar" :value="request('q')" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="estado" label="Estado" :options="\App\Models\ContactMessage::STATUSES" :value="request('estado')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
</form>
<div class="card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Data</th><th>Nome</th><th>Contacto</th><th>Assunto</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse ($messages as $m)
            <tr class="{{ $m->status === 'new' ? 'fw-semibold' : '' }}">
                <td class="small">@datetime($m->created_at)</td>
                <td><a href="{{ route('admin.messages.show', $m) }}">{{ $m->name }}</a></td>
                <td class="small">{{ $m->email }}<div class="text-muted">{{ $m->phone }}</div></td>
                <td>{{ \Illuminate\Support\Str::limit($m->subject, 60) }}</td>
                <td><x-badge :label="$m->statusLabel()" :color="['new' => 'warning', 'read' => 'info', 'answered' => 'success', 'archived' => 'secondary'][$m->status]" /></td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty-state icon="envelope" title="Sem mensagens" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $messages->links() }}</div>
@endsection
