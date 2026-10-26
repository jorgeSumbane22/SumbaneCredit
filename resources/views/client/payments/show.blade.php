@extends('layouts.client')
@section('title', 'Pagamento '.$payment->payment_number)

@section('content')
<x-page-header :title="'Pagamento '.$payment->payment_number" :breadcrumbs="['Início' => route('client.dashboard'), 'Pagamentos' => route('client.payments.index'), $payment->payment_number => null]">
    <x-badge :status="$payment->status" class="fs-6" />
    @can('receipt', $payment)
        <a href="{{ route('client.payments.receipt', $payment) }}" target="_blank" class="btn btn-success"><i class="bi bi-receipt me-1"></i>Recibo (PDF)</a>
    @endcan
</x-page-header>

@if ($payment->status->value === 'pending')
    <div class="alert alert-warning"><i class="bi bi-hourglass-split me-1"></i>Pagamento pendente de confirmação pela instituição.</div>
@elseif ($payment->status->value === 'rejected')
    <div class="alert alert-danger"><strong>Comprovativo não aceite:</strong> {{ $payment->rejection_reason }}</div>
@elseif ($payment->status->value === 'reversed')
    <div class="alert alert-dark"><strong>Pagamento estornado:</strong> {{ $payment->reversal?->reason }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <dl class="dl-grid">
                <dt>Valor</dt><dd class="fs-5 fw-bold text-navy">@money($payment->amount)</dd>
                <dt>Empréstimo</dt><dd><a href="{{ route('client.loans.show', $payment->loan) }}">{{ $payment->loan->loan_number }}</a></dd>
                <dt>Tipo</dt><dd>{{ $payment->typeLabel() }}</dd>
                <dt>Data</dt><dd>@date($payment->paid_at)</dd>
                <dt>Método</dt><dd>{{ $payment->method->label() }}</dd>
                <dt>Referência</dt><dd>{{ $payment->reference ?? '—' }}</dd>
                <dt>Recibo</dt><dd>{{ $payment->receipt_number ?? '—' }}</dd>
                <dt>Confirmado em</dt><dd>@datetime($payment->confirmed_at)</dd>
            </dl>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card"><div class="card-header"><h2 class="card-title">Imputação às prestações</h2></div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Prestação</th><th class="num">Penaliz.</th><th class="num">Encargos</th><th class="num">Juros</th><th class="num">Capital</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    @forelse ($payment->allocations as $a)
                        <tr class="{{ $a->is_reversed ? 'text-decoration-line-through text-muted' : '' }}">
                            <td>N.º {{ $a->installment->number }} (@date($a->installment->due_date))</td>
                            <td class="num">{{ \App\Support\Money::format($a->penalty, false) }}</td><td class="num">{{ \App\Support\Money::format($a->fee, false) }}</td>
                            <td class="num">{{ \App\Support\Money::format($a->interest, false) }}</td><td class="num">{{ \App\Support\Money::format($a->principal, false) }}</td>
                            <td class="num fw-semibold">{{ \App\Support\Money::format($a->total, false) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="diagram-3" title="Ainda sem imputação" text="A imputação é feita na confirmação do pagamento." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
