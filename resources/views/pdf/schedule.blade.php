@extends('pdf.layout')
@section('title', 'Plano '.$loan->loan_number)
@php use App\Support\Money; @endphp

@section('content')
<h1>Calendário de amortização @if ($schedule['provisional'])(previsional)@endif</h1>
<div class="muted">Empréstimo {{ $loan->loan_number }} · {{ $loan->client->full_name }} · {{ $loan->product->name }} · {{ Money::format($loan->principal_amount) }} · {{ $loan->rateLabel() }} · {{ $loan->amortization_method->shortLabel() }}</div>
<br>
<table class="grid">
    <thead><tr><th>N.º</th><th>Vencimento</th><th class="num">Saldo inicial</th><th class="num">Capital</th><th class="num">Juros</th><th class="num">Encargos</th><th class="num">Penalização</th><th class="num">Prestação</th><th class="num">Pago</th><th>Estado</th></tr></thead>
    <tbody>
    @php $t = ['principal' => '0', 'interest' => '0', 'fee' => '0', 'penalty' => '0', 'total' => '0', 'paid' => '0']; @endphp
    @foreach ($schedule['rows'] as $r)
        @php
            foreach ($t as $k => $v) { $t[$k] = Money::add($v, $r[$k]); }
        @endphp
        <tr>
            <td>{{ $r['number'] }}</td><td>{{ \Carbon\Carbon::parse($r['due_date'])->format('d/m/Y') }}</td>
            <td class="num">{{ Money::format($r['opening_balance'], false) }}</td>
            <td class="num">{{ Money::format($r['principal'], false) }}</td><td class="num">{{ Money::format($r['interest'], false) }}</td>
            <td class="num">{{ Money::format($r['fee'], false) }}</td><td class="num">{{ Money::format($r['penalty'], false) }}</td>
            <td class="num">{{ Money::format($r['total'], false) }}</td><td class="num">{{ Money::format($r['paid'], false) }}</td><td>{{ $r['status'] }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot><tr><td colspan="3">Totais</td>
        <td class="num">{{ Money::format($t['principal'], false) }}</td><td class="num">{{ Money::format($t['interest'], false) }}</td>
        <td class="num">{{ Money::format($t['fee'], false) }}</td><td class="num">{{ Money::format($t['penalty'], false) }}</td>
        <td class="num">{{ Money::format($t['total'], false) }}</td><td class="num">{{ Money::format($t['paid'], false) }}</td><td></td></tr></tfoot>
</table>
@if ($schedule['provisional'])
    <p class="warning">Plano previsional calculado com início na data de hoje. As datas definitivas são fixadas com a data efectiva do desembolso.</p>
@endif
@endsection
