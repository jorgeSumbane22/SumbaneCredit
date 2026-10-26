@extends('layouts.admin')
@section('title', 'Registo de auditoria #'.$log->id)

@section('content')
<x-page-header :title="'Registo de auditoria #'.$log->id" :subtitle="$log->action" :breadcrumbs="['Auditoria' => route('admin.audit.index'), '#'.$log->id => null]" />
<div class="row g-4">
    <div class="col-lg-5"><div class="card"><div class="card-body">
        <dl class="dl-grid small">
            <dt>Data/hora</dt><dd>{{ $log->created_at->format('d/m/Y H:i:s') }}</dd>
            <dt>Utilizador</dt><dd>{{ $log->user?->name ?? 'Sistema/visitante' }} @if ($log->user)({{ $log->user->email }})@endif</dd>
            <dt>Acção</dt><dd><code>{{ $log->action }}</code></dd>
            <dt>Registo afectado</dt><dd>{{ $log->subjectLabel() }}</dd>
            <dt>Resultado</dt><dd>{{ $log->result === 'success' ? 'Sucesso' : 'Falha' }}</dd>
            <dt>Descrição</dt><dd>{{ $log->description ?? '—' }}</dd>
            <dt>Origem</dt><dd>{{ $log->origin }}</dd>
            <dt>IP</dt><dd>{{ $log->ip_address ?? '—' }}</dd>
            <dt>Pedido</dt><dd class="text-break">{{ $log->method }} {{ $log->url }}</dd>
            <dt>Agente</dt><dd class="text-break">{{ $log->user_agent }}</dd>
        </dl>
    </div></div></div>
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-header"><h2 class="card-title">Valores anteriores</h2></div><div class="card-body"><pre class="small mb-0">{{ $log->old_values ? json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre></div></div>
        <div class="card"><div class="card-header"><h2 class="card-title">Valores novos / dados</h2></div><div class="card-body"><pre class="small mb-0">{{ $log->new_values ? json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre></div></div>
    </div>
</div>
@endsection
