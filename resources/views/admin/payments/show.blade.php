@extends('layouts.admin')
@section('title', 'Pagamento '.$payment->payment_number)
@php use App\Support\Money; $p = $payment; @endphp

@section('content')
<x-page-header :title="'Pagamento '.$p->payment_number" :subtitle="$p->client->full_name.' · '.$p->loan->loan_number" :breadcrumbs="['Pagamentos' => route('admin.payments.index'), $p->payment_number => null]">
    <x-badge :status="$p->status" class="fs-6" />
    @if ($p->receipt_number)<a href="{{ route('admin.payments.receipt', $p) }}" target="_blank" class="btn btn-outline-success"><i class="bi bi-receipt me-1"></i>Recibo {{ $p->receipt_number }}</a>@endif
    @if ($p->proof_path)<a href="{{ route('admin.payments.proof', $p) }}" class="btn btn-outline-secondary"><i class="bi bi-paperclip me-1"></i>Comprovativo</a>@endif
</x-page-header>

@if ($p->status->value === 'pending')
    <div class="card mb-4 border-warning"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <i class="bi bi-hourglass-split text-warning fs-4"></i>
        <div class="me-auto"><strong>Pagamento por confirmar.</strong> Verifique a recepção efectiva do valor (extracto M-Pesa/e-Mola/banco ou caixa) antes de confirmar.</div>
        @can('payments.confirm')
            <x-action-form :action="route('admin.payments.confirm', $p)" :confirm="'Confirma a recepção de '.Money::format($p->amount).'? O valor será imputado às prestações.'" button="Confirmar pagamento" icon="check2-circle" class="btn btn-success" />
            <x-action-form :action="route('admin.payments.reject', $p)" confirm="Rejeitar este pagamento/comprovativo?" reason="reason" reason-label="Motivo (comunicado ao cliente)" button="Rejeitar" class="btn btn-outline-danger" confirm-color="#DC3545" />
        @endcan
    </div></div>
@elseif ($p->status->value === 'confirmed')
    @can('payments.reverse')
        <div class="card mb-4"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <div class="me-auto small">O estorno gera movimentos compensatórios e repõe as prestações. Os estornos seguem a ordem inversa dos pagamentos.</div>
            @if ($isLatest)
                <x-action-form :action="route('admin.payments.reverse', $p)" confirm="Estornar este pagamento? Esta operação é registada na auditoria." reason="reason" reason-label="Fundamentação do estorno" button="Estornar" icon="arrow-counterclockwise" class="btn btn-outline-danger" confirm-color="#DC3545" />
            @else
                <span class="badge badge-soft-secondary">Existem pagamentos posteriores confirmados — estorne-os primeiro.</span>
            @endif
        </div></div>
    @endcan
@endif

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <dl class="dl-grid">
                <dt>Valor</dt><dd class="fs-5 fw-bold text-navy">@money($p->amount)</dd>
                <dt>Tipo</dt><dd>{{ $p->typeLabel() }}</dd>
                <dt>Empréstimo</dt><dd><a href="{{ route('admin.loans.show', $p->loan) }}">{{ $p->loan->loan_number }}</a></dd>
                <dt>Cliente</dt><dd><a href="{{ route('admin.clients.show', $p->client) }}">{{ $p->client->full_name }}</a></dd>
                <dt>Data</dt><dd>@date($p->paid_at)</dd>
                <dt>Método</dt><dd>{{ $p->method->label() }}</dd>
                <dt>Referência</dt><dd>{{ $p->reference ?? '—' }}</dd>
                <dt>Telefone pagador</dt><dd>{{ $p->payer_phone ?? '—' }}</dd>
                <dt>Origem</dt><dd>{{ $p->sourceLabel() }}</dd>
                <dt>Registado por</dt><dd>{{ $p->registrar?->name ?? '—' }} · @datetime($p->created_at)</dd>
                <dt>Confirmado por</dt><dd>{{ $p->confirmer?->name ?? '—' }} @if ($p->confirmed_at)· @datetime($p->confirmed_at)@endif</dd>
                @if ($p->rejection_reason)<dt>Rejeição</dt><dd class="text-danger">{{ $p->rejecter?->name }}: {{ $p->rejection_reason }}</dd>@endif
                @if ($p->reversal)<dt>Estorno</dt><dd class="text-danger">{{ $p->reversal->reverser?->name }} · @datetime($p->reversal->reversed_at)<br>{{ $p->reversal->reason }}</dd>@endif
                @if ($p->notes)<dt>Observações</dt><dd>{{ $p->notes }}</dd>@endif
            </dl>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card"><div class="card-header"><h2 class="card-title">Imputação</h2></div>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>Prestação</th><th class="num">Penaliz.</th><th class="num">Encargos</th><th class="num">Juros</th><th class="num">Capital</th><th class="num">Total</th></tr></thead>
                <tbody>
                @forelse ($p->allocations as $a)
                    <tr class="{{ $a->is_reversed ? 'text-decoration-line-through text-muted' : '' }}"><td>N.º {{ $a->installment->number }} (@date($a->installment->due_date))</td>
                        <td class="num">{{ Money::format($a->penalty, false) }}</td><td class="num">{{ Money::format($a->fee, false) }}</td><td class="num">{{ Money::format($a->interest, false) }}</td>
                        <td class="num">{{ Money::format($a->principal, false) }}</td><td class="num fw-semibold">{{ Money::format($a->total, false) }}</td></tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="diagram-3" title="Sem imputação" text="A imputação ocorre na confirmação." /></td></tr>
                @endforelse
                </tbody>
            </table></div>
            @if (! empty($p->meta['waivers']))
                <div class="card-body border-top small">Juros dispensados (liquidação antecipada): @money(Money::sum($p->meta['waivers']))</div>
            @endif
        </div>
    </div>
</div>
@endsection
