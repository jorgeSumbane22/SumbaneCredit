@extends('layouts.admin')
@section('title', $report['title'])

@section('content')
<x-page-header :title="$report['title']" :subtitle="$report['period_label']" :breadcrumbs="['Relatórios' => route('admin.reports.index'), $report['title'] => null]">
    @can('reports.export')
        <a href="{{ route('admin.reports.pdf', ['type' => $type] + request()->query()) }}" target="_blank" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
        <a href="{{ route('admin.reports.excel', ['type' => $type] + request()->query()) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    @endcan
</x-page-header>

<form method="GET" class="filter-bar row g-2 align-items-end">
    <div class="col-sm-4 col-md-3">
        <label class="form-label">Período</label>
        <select name="periodo" class="form-select" onchange="this.form.querySelectorAll('.custom-dates').forEach(e => e.classList.toggle('d-none', this.value !== 'custom'))">
            @foreach (\App\Support\Period::PRESETS as $k => $v)<option value="{{ $k }}" @selected($preset === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    <div class="col-sm-4 col-md-2 custom-dates {{ $preset === 'custom' ? '' : 'd-none' }}"><label class="form-label">De</label><input type="date" name="de" class="form-control" value="{{ $report['from']->toDateString() }}"></div>
    <div class="col-sm-4 col-md-2 custom-dates {{ $preset === 'custom' ? '' : 'd-none' }}"><label class="form-label">Até</label><input type="date" name="ate" class="form-control" value="{{ $report['to']->toDateString() }}"></div>
    @foreach ($report['filters'] as $name => $options)
        <div class="col-sm-4 col-md-2"><x-select :name="$name" :label="['estado' => 'Estado', 'kyc' => 'Identificação', 'metodo' => 'Método'][$name] ?? ucfirst($name)" :options="$options" :value="request($name)" placeholder="Todos" col="mb-0" /></div>
    @endforeach
    @if (in_array($type, ['clients', 'loans', 'collections'], true))
        <div class="col-auto"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="todos" value="1" id="todos" @checked(request()->boolean('todos'))><label class="form-check-label small" for="todos">Ignorar período</label></div></div>
    @endif
    <div class="col-auto"><button class="btn btn-primary" data-no-loading><i class="bi bi-funnel me-1"></i>Aplicar</button></div>
</form>

@if ($report['summary'])
    <div class="row g-3 mb-3">
        @foreach ($report['summary'] as $label => $value)
            <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3"><div class="stat-label">{{ $label }}</div><div class="fw-bold text-navy tabular">{{ $value }}</div></div></div></div>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr>@foreach ($report['columns'] as $col)<th class="{{ in_array($col, $report['numeric'], true) ? 'num' : '' }}">{{ $col }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($report['rows'] as $row)
                <tr>@foreach ($row as $i => $cell)<td class="{{ in_array($report['columns'][$i] ?? '', $report['numeric'], true) ? 'num' : '' }}">{{ $cell ?? '—' }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}"><x-empty-state icon="bar-chart" title="Sem registos" text="Ajuste o período ou os filtros." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if (! empty($report['truncated']))
        <div class="card-body border-top small text-muted">A mostrar {{ count($report['rows']) }} de {{ $report['total_rows'] }} registos. Exporte para Excel para obter a lista completa.</div>
    @endif
</div>
@endsection
