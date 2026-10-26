@extends('pdf.layout')
@section('title', 'Recibo '.$payment->receipt_number)
@php use App\Support\Money; @endphp

@section('content')
<table><tr>
    <td><h1>Recibo de pagamento</h1><div class="muted">N.º {{ $payment->receipt_number }} · Pagamento {{ $payment->payment_number }}</div></td>
    <td class="num">@if ($payment->status->value === 'reversed')<span class="stamp">ESTORNADO</span>@endif</td>
</tr></table>
<br>
<div class="box highlight">
    <table><tr>
        <td>Recebemos de <strong>{{ $payment->client->full_name }}</strong> ({{ $payment->client->documentTypeLabel() }} {{ $payment->client->document_number }}) a quantia de:</td>
        <td class="num" style="font-size:16pt;font-weight:bold;color:#142D4E">{{ Money::format($payment->amount) }}</td>
    </tr></table>
</div>
<table class="kv">
    <tr><td class="k">Empréstimo</td><td>{{ $payment->loan->loan_number }} ({{ $payment->loan->product->name }})</td></tr>
    <tr><td class="k">Tipo</td><td>{{ $payment->typeLabel() }}</td></tr>
    <tr><td class="k">Data do pagamento</td><td>{{ $payment->paid_at->format('d/m/Y') }}</td></tr>
    <tr><td class="k">Método / referência</td><td>{{ $payment->method->label() }} @if ($payment->reference)— {{ $payment->reference }}@endif</td></tr>
    <tr><td class="k">Confirmado em</td><td>{{ $payment->confirmed_at?->format('d/m/Y H:i') }} por {{ $payment->confirmer?->name }}</td></tr>
</table>

<h2>Imputação do pagamento</h2>
<table class="grid">
    <thead><tr><th>Prestação</th><th>Vencimento</th><th class="num">Penalizações</th><th class="num">Encargos</th><th class="num">Juros</th><th class="num">Capital</th><th class="num">Total</th></tr></thead>
    <tbody>
    @foreach ($payment->allocations as $a)
        <tr><td>N.º {{ $a->installment->number }}</td><td>{{ $a->installment->due_date->format('d/m/Y') }}</td>
            <td class="num">{{ Money::format($a->penalty, false) }}</td><td class="num">{{ Money::format($a->fee, false) }}</td>
            <td class="num">{{ Money::format($a->interest, false) }}</td><td class="num">{{ Money::format($a->principal, false) }}</td><td class="num">{{ Money::format($a->total, false) }}</td></tr>
    @endforeach
    </tbody>
</table>
@if (! empty($payment->meta['waivers']))
    <p class="small">Juros futuros dispensados por liquidação antecipada: {{ Money::format(Money::sum($payment->meta['waivers'])) }}.</p>
@endif
@if ($payment->reversal)
    <p class="warning">Pagamento estornado em {{ $payment->reversal->reversed_at->format('d/m/Y H:i') }}. Motivo: {{ $payment->reversal->reason }}</p>
@endif
<p class="small muted">Recibo emitido electronicamente pelo sistema, com base no pagamento confirmado pela instituição.</p>
@endsection
