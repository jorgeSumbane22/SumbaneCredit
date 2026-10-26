@extends('layouts.admin')
@section('title', 'Cobranças')

@section('content')
<x-page-header title="Cobranças" subtitle="Processos abertos automaticamente quando há prestações vencidas e encerrados quando o atraso é regularizado.">
    @can('reports.view')<a href="{{ route('admin.reports.show', 'collections') }}" class="btn btn-outline-primary"><i class="bi bi-bar-chart me-1"></i>Relatório</a>@endcan
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg"><x-stat-card label="Processos abertos" :value="$stats['open']" icon="folder2-open" color="red" /></div>
    <div class="col-6 col-lg"><x-stat-card label="Valor em atraso" :value="\App\Support\Money::format($stats['amount'])" icon="cash" color="orange" /></div>
    <div class="col-6 col-lg"><x-stat-card label="Acções para hoje" :value="$stats['today']" icon="calendar-event" :href="route('admin.collections.index', ['acao' => 'hoje'])" /></div>
    <div class="col-6 col-lg"><x-stat-card label="Promessas pendentes" :value="$stats['promises']" icon="hand-thumbs-up" color="green" /></div>
    <div class="col-6 col-lg"><x-stat-card label="Promessas quebradas (30 d)" :value="$stats['broken']" icon="hand-thumbs-down" color="red" /></div>
</div>

<form class="filter-bar row g-2 align-items-end" method="GET">
    <div class="col-md-3"><x-input name="q" label="Pesquisar" :value="request('q')" placeholder="Empréstimo ou cliente" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="estado" label="Estado" :options="['abertos' => 'Abertos', 'todos' => 'Todos'] + \App\Models\CollectionCase::STATUSES" :value="$estado" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="escalao" label="Atraso" :options="['30' => '1–30 dias', '60' => '31–60 dias', '90' => '61–90 dias', '91' => '+90 dias']" :value="request('escalao')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-sm-4 col-md-2"><x-select name="responsavel" label="Responsável" :options="['eu' => 'Os meus', 'sem' => 'Sem responsável']" :value="request('responsavel')" placeholder="Todos" col="mb-0" /></div>
    <div class="col-auto"><button class="btn btn-primary" data-no-loading>Filtrar</button></div>
</form>

<div class="card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Empréstimo</th><th>Cliente / contacto</th><th>Atraso</th><th class="num">Valor em atraso</th><th>Responsável</th><th>Último contacto</th><th>Próxima acção</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse ($cases as $c)
            <tr>
                <td><a href="{{ route('admin.collections.show', $c) }}" class="fw-semibold">{{ $c->loan->loan_number }}</a></td>
                <td>{{ $c->client->full_name }}<div class="small text-muted">{{ $c->client->phone }}</div></td>
                <td><span class="badge badge-soft-{{ $c->days_past_due > 60 ? 'danger' : ($c->days_past_due > 15 ? 'warning' : 'secondary') }}">{{ $c->days_past_due }} dias</span></td>
                <td class="num fw-semibold">@money($c->overdue_amount)</td>
                <td class="small">{{ $c->assignee?->name ?? '—' }}</td>
                <td class="small">{{ $c->last_contact_at?->diffForHumans() ?? 'Nunca' }} <span class="text-muted">({{ $c->contacts_count }})</span></td>
                <td class="small {{ $c->next_action_at && $c->next_action_at->isPast() && $c->status !== 'resolved' ? 'text-danger fw-semibold' : '' }}">@datetime($c->next_action_at)</td>
                <td><x-badge :label="$c->statusLabel()" :color="$c->statusColor()" />@if ($c->promises->isNotEmpty())<div class="small text-muted">Promessa: @date($c->promises->first()->promised_date)</div>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty-state icon="emoji-smile" title="Sem processos de cobrança" /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $cases->links() }}</div>
@endsection
