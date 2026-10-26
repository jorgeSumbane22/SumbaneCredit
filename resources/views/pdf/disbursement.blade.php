@extends('pdf.layout')
@section('title', 'Desembolso '.$disbursement->disbursement_number)
@php use App\Support\Money; $d = $disbursement; @endphp

@section('content')
<h1>Comprovativo de desembolso</h1>
<div class="muted">N.º {{ $d->disbursement_number }} · Estado: {{ $d->status->label() }}</div>
@if ($d->status->value !== 'completed')
    <p class="warning">Este desembolso ainda não foi efectuado. O presente documento não comprova a entrega de fundos.</p>
@endif
<h2>Beneficiário</h2>
<table class="kv">
    <tr><td class="k">Cliente</td><td>{{ $d->loan->client->full_name }} ({{ $d->loan->client->client_number }})</td></tr>
    <tr><td class="k">Documento</td><td>{{ $d->loan->client->documentTypeLabel() }} {{ $d->loan->client->document_number }}</td></tr>
    <tr><td class="k">Empréstimo / contrato</td><td>{{ $d->loan->loan_number }} / {{ $d->loan->contract_number }} — {{ $d->loan->product->name }}</td></tr>
</table>
<h2>Operação</h2>
<table class="kv">
    <tr><td class="k">Capital contratado</td><td>{{ Money::format($d->gross_amount) }}</td></tr>
    <tr><td class="k">Encargos deduzidos</td><td>{{ Money::format($d->deducted_fees) }}</td></tr>
    <tr><td class="k">Valor líquido entregue</td><td><strong>{{ Money::format($d->net_amount) }}</strong></td></tr>
    <tr><td class="k">Método</td><td>{{ $d->method->label() }}</td></tr>
    <tr><td class="k">Conta/número de destino</td><td>{{ $d->destination_account ?? '—' }}</td></tr>
    <tr><td class="k">Referência</td><td>{{ $d->reference ?? '—' }}</td></tr>
    <tr><td class="k">Data do desembolso</td><td>{{ $d->disbursed_at?->format('d/m/Y') ?? '—' }}</td></tr>
</table>
<h2>Responsáveis</h2>
<table class="kv">
    <tr><td class="k">Registado por</td><td>{{ $d->requester?->name }} em {{ $d->requested_at?->format('d/m/Y H:i') }}</td></tr>
    <tr><td class="k">Autorizado por</td><td>{{ $d->authorizer?->name ?? '—' }} @if ($d->authorized_at)em {{ $d->authorized_at->format('d/m/Y H:i') }}@endif</td></tr>
    <tr><td class="k">Executado por</td><td>{{ $d->executor?->name ?? '—' }} @if ($d->completed_at)em {{ $d->completed_at->format('d/m/Y H:i') }}@endif</td></tr>
</table>
<table class="sign"><tr><td><div class="line">Pela instituição</div></td><td><div class="line">O cliente (recebi)</div></td></tr></table>
@endsection
