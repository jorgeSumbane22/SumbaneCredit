@extends('layouts.admin')
@section('title', 'Auditoria')

@section('content')
<x-page-header title="Auditoria" subtitle="Registo imutável das operações relevantes (utilizador, acção, registo, data/hora, resultado e origem)." />

<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-3"><x-input name="q" label="Pesquisar" :value="request('q')" placeholder="Acção ou descrição" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="modulo" label="Módulo" :options="$modules" :value="request('modulo')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="utilizador" label="Utilizador" :options="$users->all()" :value="request('utilizador')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-4 col-md-1"><x-select name="resultado" label="Resultado" :options="['success' => 'Sucesso', 'failure' => 'Falha']" :value="request('resultado')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-6 col-md-1"><x-input name="de" type="date" label="De" :value="request('de')" col="mb-0" /></div>
    <div class="col-sm-6 col-md-1"><x-input name="ate" type="date" label="Até" :value="request('ate')" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
</form>

<div class="card"><div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead><tr><th>Data/hora</th><th>Utilizador</th><th>Acção</th><th>Registo</th><th>Descrição</th><th>Origem / IP</th><th>Resultado</th></tr></thead>
        <tbody>
        @forelse ($logs as $l)
            <tr>
                <td class="small text-nowrap"><a href="{{ route('admin.audit.show', $l) }}">{{ $l->created_at->format('d/m/Y H:i:s') }}</a></td>
                <td class="small">{{ $l->user?->name ?? 'Sistema/visitante' }}</td>
                <td class="small"><code>{{ $l->action }}</code></td>
                <td class="small">{{ $l->subjectLabel() }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($l->description, 70) }}</td>
                <td class="small">{{ $l->origin }} {{ $l->ip_address }}</td>
                <td><x-badge :label="$l->result === 'success' ? 'Sucesso' : 'Falha'" :color="$l->result === 'success' ? 'success' : 'danger'" /></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty-state icon="journal-check" title="Sem registos" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
