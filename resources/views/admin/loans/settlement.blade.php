@extends('layouts.admin')
@section('title', 'Liquidação antecipada')

@section('content')
<x-page-header title="Liquidação antecipada" :subtitle="$loan->loan_number.' · '.$loan->client->full_name" :breadcrumbs="['Empréstimos' => route('admin.loans.index'), $loan->loan_number => route('admin.loans.show', $loan), 'Liquidação' => null]" />

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card"><div class="card-header"><h2 class="card-title">Montante de liquidação a @date($asOf)</h2></div><div class="card-body">
            <form method="GET" class="d-flex gap-2 mb-3">
                <input type="date" name="data" class="form-control" value="{{ $asOf->toDateString() }}" max="{{ today()->toDateString() }}">
                <button class="btn btn-outline-primary" data-no-loading>Recalcular</button>
            </form>
            <dl class="dl-grid">
                <dt>Capital em dívida</dt><dd>@money($quote['principal'])</dd>
                <dt>Juros devidos</dt><dd>@money($quote['interest'])</dd>
                <dt>Encargos</dt><dd>@money($quote['fee'])</dd>
                <dt>Penalizações</dt><dd>@money($quote['penalty'])</dd>
                <dt>Juros dispensados</dt><dd class="text-success">@money($quote['interest_waived'])</dd>
                <dt class="fs-5">Total a pagar</dt><dd class="fs-4 fw-bold text-navy">@money($quote['total'])</dd>
            </dl>
            <div class="alert alert-light border small mb-0">Regra contratual: {{ $quote['rule_label'] }}. Na confirmação, o montante é recalculado à data do pagamento; tem de coincidir com o valor registado.</div>
        </div></div>
    </div>
    <div class="col-lg-6">
        @can('payments.register')
        <div class="card"><div class="card-header"><h2 class="card-title">Registar pagamento de liquidação</h2></div><div class="card-body">
            <form method="POST" action="{{ route('admin.loans.settle', $loan) }}" data-confirm="Registar o pagamento de liquidação de {{ \App\Support\Money::format($quote['total']) }}?">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <x-input name="paid_at" type="date" label="Data do pagamento" :value="$asOf->toDateString()" max="{{ today()->toDateString() }}" required />
                <x-select name="method" label="Método" :options="\App\Enums\PaymentMethod::options()" required />
                <x-input name="reference" label="Referência (obrigatória excepto numerário)" />
                <x-textarea name="notes" label="Observações" rows="2" />
                <button class="btn btn-success"><i class="bi bi-flag me-1"></i>Registar liquidação</button>
            </form>
        </div></div>
        @endcan
    </div>
</div>
@endsection
