@extends('pdf.layout')
@section('title', $report['title'])

@section('content')
<h1>{{ $report['title'] }}</h1>
<div class="muted">{{ $report['period_label'] }} · Gerado por {{ auth()->user()?->name }}</div>
@if (! empty($report['summary']))
    <br>
    <table class="kv">
        @foreach (array_chunk($report['summary'], 2, true) as $pair)
            <tr>@foreach ($pair as $label => $value)<td class="k">{{ $label }}</td><td><strong>{{ $value }}</strong></td>@endforeach</tr>
        @endforeach
    </table>
@endif
<br>
<table class="grid">
    <thead><tr>@foreach ($report['columns'] as $col)<th class="{{ in_array($col, $report['numeric'] ?? [], true) ? 'num' : '' }}">{{ $col }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($report['rows'] as $row)
        <tr>@foreach (array_values($row) as $i => $cell)<td class="{{ in_array($report['columns'][$i] ?? '', $report['numeric'] ?? [], true) ? 'num' : '' }}">{{ $cell }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($report['columns']) }}" class="muted">Sem registos para o período e filtros seleccionados.</td></tr>
    @endforelse
    </tbody>
</table>
@if (! empty($report['truncated']))
    <p class="small muted">Apresentados os primeiros {{ count($report['rows']) }} registos. Utilize a exportação Excel para a listagem completa.</p>
@endif
@endsection
