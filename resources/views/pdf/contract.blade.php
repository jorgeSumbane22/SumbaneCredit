@extends('pdf.layout')
@section('title', 'Contrato '.$loan->contract_number)

@php
    use App\Support\Money;
    $c = $loan->client;
    $snap = $loan->terms_snapshot ?? [];
@endphp

@section('content')
<table><tr>
    <td><h1>Contrato de Mútuo (Microcrédito)</h1><div class="muted">Contrato n.º {{ $loan->contract_number }} · Empréstimo n.º {{ $loan->loan_number }}</div></td>
    <td class="num">@if ($loan->status->value === 'awaiting_contract')<span class="stamp">MINUTA</span>@endif</td>
</tr></table>

@if ($loan->status->value === 'awaiting_contract')
    <p class="warning">Minuta para consulta prévia do cliente. O contrato só produz efeitos após assinatura pelas partes e formalização junto da instituição.</p>
@endif

<h2>1. Partes</h2>
<table class="kv">
    <tr><td class="k">Mutuante</td><td>{{ $institution['legal_name'] ?: $institution['name'] }}@if ($institution['nuit']), NUIT {{ $institution['nuit'] }}@endif @if ($institution['address']), com sede em {{ $institution['address'] }}@endif</td></tr>
    @if ($institution['registration_info'])<tr><td class="k">Registo/Autorização</td><td>{{ $institution['registration_info'] }}</td></tr>@endif
    <tr><td class="k">Mutuário</td><td>{{ $c->full_name }}, portador(a) de {{ $c->documentTypeLabel() }} n.º {{ $c->document_number }}@if ($c->nuit), NUIT {{ $c->nuit }}@endif</td></tr>
    <tr><td class="k">Morada do mutuário</td><td>{{ $c->address?->oneLine() ?: '—' }}</td></tr>
    <tr><td class="k">Contactos</td><td>{{ collect([$c->phone, $c->email])->filter()->implode(' · ') }}</td></tr>
</table>

<h2>2. Condições financeiras</h2>
<table class="kv">
    <tr><td class="k">Produto</td><td>{{ $snap['product']['name'] ?? $loan->product->name }} (versão {{ $snap['product']['version'] ?? '—' }} das condições)</td></tr>
    <tr><td class="k">Capital mutuado</td><td><strong>{{ Money::format($loan->principal_amount) }}</strong></td></tr>
    <tr><td class="k">Taxa de juro</td><td>{{ $loan->rateLabel() }} — taxa nominal, convertida proporcionalmente para o período de pagamento ({{ str_replace('.', ',', Money::round(Money::mul($loan->rate_per_period, '100'), 4)) }}% por prestação)</td></tr>
    <tr><td class="k">Método de cálculo</td><td>{{ $loan->amortization_method->label() }}</td></tr>
    <tr><td class="k">Prestações</td><td>{{ $loan->term }} prestações, periodicidade {{ strtolower($loan->repayment_frequency->label()) }}</td></tr>
    <tr><td class="k">Encargos</td><td>{{ $snap['fees_label'] ?? '—' }}</td></tr>
    <tr><td class="k">Encargos deduzidos no desembolso</td><td>{{ Money::format($loan->upfront_fees) }} (valor líquido a entregar: {{ Money::format(Money::sub($loan->principal_amount, $loan->upfront_fees)) }})</td></tr>
    <tr><td class="k">Total de juros</td><td>{{ Money::format($loan->total_interest) }}</td></tr>
    <tr><td class="k">Montante total a reembolsar</td><td><strong>{{ Money::format($loan->total_contract_amount) }}</strong></td></tr>
    <tr><td class="k">Mora</td><td>{{ $snap['penalty_label'] ?? '—' }}</td></tr>
    <tr><td class="k">Liquidação antecipada</td><td>{{ $snap['early_settlement_label'] ?? '—' }}</td></tr>
    <tr><td class="k">Finalidade declarada</td><td>{{ $loan->application?->purpose }}</td></tr>
</table>

<h2>3. Cláusulas</h2>
<div class="clause"><strong>3.1.</strong> O plano de prestações é calculado a partir da data efectiva do desembolso; a primeira prestação vence um período após essa data. Até ao desembolso, o plano apresentado é previsional.</div>
<div class="clause"><strong>3.2.</strong> Os pagamentos são imputados, em cada prestação e por ordem de vencimento, a penalizações, encargos, juros e capital. Pagamentos superiores à prestação vencida são aplicados às prestações seguintes.</div>
<div class="clause"><strong>3.3.</strong> Os pagamentos podem ser efectuados por M-Pesa, e-Mola, transferência ou depósito bancário, ou em numerário nos balcões da instituição, devendo ser indicada a referência da operação. O pagamento só se considera efectuado após confirmação pela instituição, que emite o respectivo recibo.</div>
<div class="clause"><strong>3.4.</strong> O mutuário declara que as informações prestadas no pedido são verdadeiras e autoriza o tratamento dos seus dados pessoais para efeitos de gestão do contrato.</div>
@if (! empty($snap['contract_conditions']))
    <div class="clause"><strong>3.5. Condições específicas do produto.</strong> {!! nl2br(e($snap['contract_conditions'])) !!}</div>
@endif

@if ($loan->guarantors->isNotEmpty() || $loan->collaterals->isNotEmpty())
<h2>4. Garantias</h2>
@foreach ($loan->guarantors as $g)
    <div class="small">Fiador: {{ $g->full_name }}, documento {{ $g->document_number }}, telefone {{ $g->phone }}@if ($g->relationship) ({{ $g->relationship }})@endif.</div>
@endforeach
@foreach ($loan->collaterals as $col)
    <div class="small">Garantia: {{ $col->typeLabel() }} — {{ $col->description }} (valor estimado {{ Money::format($col->estimated_value) }}).</div>
@endforeach
@endif

<h2>Anexo — Plano de prestações @if ($schedule['provisional'])(previsional)@endif</h2>
<table class="grid">
    <thead><tr><th>N.º</th><th>Vencimento</th><th class="num">Capital</th><th class="num">Juros</th><th class="num">Encargos</th><th class="num">Prestação</th></tr></thead>
    <tbody>
    @foreach ($schedule['rows'] as $r)
        <tr><td>{{ $r['number'] }}</td><td>{{ \Carbon\Carbon::parse($r['due_date'])->format('d/m/Y') }}</td><td class="num">{{ Money::format($r['principal'], false) }}</td><td class="num">{{ Money::format($r['interest'], false) }}</td><td class="num">{{ Money::format($r['fee'], false) }}</td><td class="num">{{ Money::format($r['total'], false) }}</td></tr>
    @endforeach
    </tbody>
</table>

<table class="sign">
    <tr>
        <td><div class="line">Pelo Mutuante</div></td>
        <td><div class="line">O Mutuário</div></td>
        @if ($loan->guarantors->isNotEmpty())<td><div class="line">O(s) Fiador(es)</div></td>@endif
    </tr>
</table>
<p class="small muted">Local e data: ____________________, ____/____/________. Documento gerado electronicamente; não contém assinatura digital nem substitui a assinatura das partes.</p>
@endsection
