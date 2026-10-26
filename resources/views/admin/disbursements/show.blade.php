@extends('layouts.admin')
@section('title', 'Desembolso '.$d->disbursement_number)

@section('content')
<x-page-header :title="'Desembolso '.$d->disbursement_number" :subtitle="$d->loan->loan_number.' · '.$d->loan->client->full_name" :breadcrumbs="['Desembolsos' => route('admin.disbursements.index'), $d->disbursement_number => null]">
    <x-badge :status="$d->status" class="fs-6" />
    <a href="{{ route('admin.disbursements.pdf', $d) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i>Comprovativo</a>
    @if ($d->proof_path)<a href="{{ route('admin.disbursements.proof', $d) }}" class="btn btn-outline-secondary"><i class="bi bi-paperclip me-1"></i>Ficheiro da operação</a>@endif
</x-page-header>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <dl class="dl-grid">
                <dt>Empréstimo</dt><dd><a href="{{ route('admin.loans.show', $d->loan) }}">{{ $d->loan->loan_number }}</a> ({{ $d->loan->status->label() }})</dd>
                <dt>Capital</dt><dd>@money($d->gross_amount)</dd>
                <dt>Encargos deduzidos</dt><dd>@money($d->deducted_fees)</dd>
                <dt>Valor líquido</dt><dd class="fs-5 fw-bold text-navy">@money($d->net_amount)</dd>
                <dt>Método</dt><dd>{{ $d->method->label() }}</dd>
                <dt>Destino</dt><dd>{{ $d->destination_account ?? '—' }}</dd>
                <dt>Referência</dt><dd>{{ $d->reference ?? '—' }}</dd>
                <dt>Data do desembolso</dt><dd>@date($d->disbursed_at)</dd>
                <dt>Registado por</dt><dd>{{ $d->requester?->name }} · @datetime($d->requested_at)</dd>
                <dt>Autorizado por</dt><dd>{{ $d->authorizer?->name ?? '—' }} @if ($d->authorized_at)· @datetime($d->authorized_at)@endif</dd>
                <dt>Executado por</dt><dd>{{ $d->executor?->name ?? '—' }} @if ($d->completed_at)· @datetime($d->completed_at)@endif</dd>
                @if ($d->notes)<dt>Observações</dt><dd>{!! nl2br(e($d->notes)) !!}</dd>@endif
                @if ($d->cancellation_reason)<dt>Motivo do cancelamento</dt><dd class="text-danger">{{ $d->cancellation_reason }}</dd>@endif
            </dl>
        </div></div>
    </div>
    <div class="col-lg-6">
        @if ($d->status->value === 'pending')
            <div class="card mb-3"><div class="card-header"><h2 class="card-title">Autorização</h2></div><div class="card-body">
                @can('disbursements.authorize')
                    @if ($d->requested_by === auth()->id())
                        <div class="alert alert-warning small mb-0">Registou este desembolso; a autorização tem de ser dada por outro utilizador (segregação de funções).</div>
                    @else
                        <form method="POST" action="{{ route('admin.disbursements.authorize', $d) }}" data-confirm="Autorizar o desembolso de {{ \App\Support\Money::format($d->net_amount) }}?">@csrf
                            <x-textarea name="notes" label="Observações" rows="2" />
                            <button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Autorizar</button>
                        </form>
                    @endif
                @else
                    <p class="small text-muted mb-0">Aguarda autorização por um utilizador com permissão.</p>
                @endcan
            </div></div>
        @elseif ($d->status->value === 'authorized')
            <div class="card mb-3"><div class="card-header"><h2 class="card-title">Confirmar execução</h2></div><div class="card-body">
                @can('disbursements.execute')
                    <form method="POST" action="{{ route('admin.disbursements.complete', $d) }}" enctype="multipart/form-data" data-confirm="Confirma que os fundos foram efectivamente transferidos/entregues ao cliente? Esta operação activa o empréstimo.">@csrf
                        <x-input name="reference" label="Referência da operação" :required="$d->method->requiresReference()" />
                        <x-input name="disbursed_at" type="date" label="Data efectiva" :value="today()->toDateString()" max="{{ today()->toDateString() }}" required />
                        <x-input name="proof" type="file" label="Comprovativo (opcional)" accept=".pdf,.jpg,.jpeg,.png,.webp" />
                        <button class="btn btn-success"><i class="bi bi-send-check me-1"></i>Confirmar desembolso efectuado</button>
                    </form>
                @else
                    <p class="small text-muted mb-0">Aguarda confirmação da execução pela tesouraria/caixa.</p>
                @endcan
            </div></div>
        @endif
        @if (in_array($d->status->value, ['pending', 'authorized'], true))
            @canany(['disbursements.authorize', 'disbursements.request'])
                <x-action-form :action="route('admin.disbursements.cancel', $d)" confirm="Cancelar este desembolso?" reason="reason" button="Cancelar desembolso" icon="x-circle" class="btn btn-outline-danger" confirm-color="#DC3545" />
            @endcanany
        @endif
    </div>
</div>
@endsection
