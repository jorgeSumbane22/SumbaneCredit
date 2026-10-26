@extends('layouts.client')
@section('title', 'Enviar comprovativo de pagamento')

@section('content')
<x-page-header title="Enviar comprovativo de pagamento" subtitle="Após verificação pela instituição, o pagamento é confirmado e o recibo fica disponível." :breadcrumbs="['Início' => route('client.dashboard'), 'Pagamentos' => route('client.payments.index'), 'Comprovativo' => null]" />

@if ($loans->isEmpty())
    <div class="card"><x-empty-state icon="briefcase" title="Não tem empréstimos activos" /></div>
@else
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <form method="POST" action="{{ route('client.payments.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <x-select name="loan_id" label="Empréstimo" :options="$loans->mapWithKeys(fn ($l) => [$l->id => $l->loan_number.' — em dívida '.\App\Support\Money::format($l->total_outstanding)])->all()" :value="$selected" required />
                <div class="row">
                    <x-input name="amount" type="number" step="0.01" min="1" label="Valor pago (MZN)" required col="col-md-6 mb-3" />
                    <x-input name="paid_at" type="date" label="Data do pagamento" :value="today()->toDateString()" max="{{ today()->toDateString() }}" required col="col-md-6 mb-3" />
                    <x-select name="method" label="Método" :options="collect(\App\Enums\PaymentMethod::options())->except('cash')->all()" required col="col-md-6 mb-3" />
                    <x-input name="reference" label="Referência / ID da transacção" required col="col-md-6 mb-3" help="Ex.: código da transacção M-Pesa/e-Mola ou n.º do talão bancário." />
                    <x-input name="payer_phone" label="Número de onde foi efectuado o pagamento" col="col-md-6 mb-3" />
                    <x-input name="proof" type="file" label="Comprovativo" required accept=".pdf,.jpg,.jpeg,.png,.webp" col="col-md-6 mb-3" />
                </div>
                <x-textarea name="notes" label="Observações" rows="2" />
                <button class="btn btn-success" type="submit"><i class="bi bi-send me-1"></i>Submeter comprovativo</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body small">
            <h6 class="fw-bold text-navy">Importante</h6>
            <ul class="mb-0">
                <li>O comprovativo <strong>não</strong> é considerado pagamento confirmado até ser verificado pela instituição.</li>
                <li>Cada referência só pode ser registada uma vez.</li>
                <li>O pagamento é imputado às prestações mais antigas: penalizações, encargos, juros e capital.</li>
                <li>Para liquidar antecipadamente todo o empréstimo, contacte a instituição para obter o montante de liquidação.</li>
            </ul>
        </div></div>
    </div>
</div>
@endif
@endsection
