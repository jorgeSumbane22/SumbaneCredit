@extends('pdf.layout')
@section('title', 'Extracto '.$loan->loan_number)
@php use App\Support\Money; @endphp

@section('content')
<h1>Extracto do empréstimo</h1>
<div class="muted">{{ $loan->loan_number }} · Contrato {{ $loan->contract_number }} · {{ $loan->client->full_name }} ({{ $loan->client->client_number }})</div>

<h2>Resumo à data</h2>
<table class="kv">
    <tr><td class="k">Estado</td><td>{{ $loan->status->label() }}</td><td class="k">Data do desembolso</td><td>{{ $loan->disbursed_at?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><td class="k">Capital contratado</td><td>{{ Money::format($loan->principal_amount) }}</td><td class="k">Total contratado</td><td>{{ Money::format($loan->total_contract_amount) }}</td></tr>
    <tr><td class="k">Total pago</td><td>{{ Money::format($loan->total_paid) }}</td><td class="k">Saldo em dívida</td><td><strong>{{ Money::format($loan->total_outstanding) }}</strong></td></tr>
    <tr><td class="k">Capital em dívida</td><td>{{ Money::format($loan->principal_outstanding) }}</td><td class="k">Dias em atraso</td><td>{{ $loan->days_past_due }}</td></tr>
</table>

<h2>Pagamentos</h2>
<table class="grid">
    <thead><tr><th>Data</th><th>Recibo</th><th>Método</th><th>Referência</th><th>Estado</th><th class="num">Valor</th></tr></thead>
    <tbody>
    @forelse ($payments as $p)
        <tr><td>{{ $p->paid_at->format('d/m/Y') }}</td><td>{{ $p->receipt_number }}</td><td>{{ $p->method->label() }}</td><td>{{ $p->reference ?? '—' }}</td><td>{{ $p->status->label() }}</td><td class="num">{{ Money::format($p->amount, false) }}</td></tr>
    @empty
        <tr><td colspan="6" class="muted">Sem pagamentos confirmados.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Movimentos do razão</h2>
<table class="grid">
    <thead><tr><th>Data</th><th>N.º</th><th>Descrição</th><th>Componente</th><th class="num">Débito</th><th class="num">Crédito</th></tr></thead>
    <tbody>
    @foreach ($transactions as $t)
        <tr><td>{{ $t->posted_at->format('d/m/Y') }}</td><td class="small">{{ $t->transaction_number }}</td><td>{{ $t->typeLabel() }}</td><td>{{ $t->componentLabel() }}</td>
            <td class="num">{{ $t->direction === 'debit' ? Money::format($t->amount, false) : '' }}</td><td class="num">{{ $t->direction === 'credit' ? Money::format($t->amount, false) : '' }}</td></tr>
    @endforeach
    </tbody>
</table>
@endsection
