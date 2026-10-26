@extends('layouts.client')
@section('title', 'Pedido '.$application->application_number)

@section('content')
<x-page-header :title="'Pedido '.$application->application_number" :subtitle="$application->product->name" :breadcrumbs="['Início' => route('client.dashboard'), 'Pedidos' => route('client.applications.index'), $application->application_number => null]">
    <x-badge :status="$application->status" class="fs-6" />
    @can('cancel', $application)
        <x-action-form :action="route('client.applications.cancel', $application)" confirm="Pretende cancelar este pedido? Esta acção não pode ser desfeita." reason="reason" reason-label="Motivo do cancelamento" button="Cancelar pedido" icon="x-circle" class="btn btn-outline-danger" confirm-color="#DC3545" />
    @endcan
</x-page-header>

@if ($application->status->value === 'pending_documents')
    <div class="card border-warning mb-4">
        <div class="card-header bg-warning-subtle"><h2 class="card-title"><i class="bi bi-exclamation-circle me-1"></i>Informação adicional solicitada</h2></div>
        <div class="card-body">
            <p class="mb-3">{{ $application->info_request }}</p>
            <form method="POST" action="{{ route('client.applications.provide-info', $application) }}" enctype="multipart/form-data">
                @csrf
                <x-textarea name="message" label="Resposta / informação" rows="3" />
                <div data-repeater data-max="6">
                    <div class="d-flex justify-content-between mb-2"><strong class="small">Documentos</strong><button type="button" class="btn btn-sm btn-outline-primary" data-repeater-add><i class="bi bi-plus"></i> Adicionar</button></div>
                    <div data-repeater-list>@include('loans.partials.document-row', ['i' => 0, 'field' => 'documents'])</div>
                    <template>@include('loans.partials.document-row', ['i' => '__INDEX__', 'field' => 'documents'])</template>
                </div>
                <button class="btn btn-success mt-2" type="submit"><i class="bi bi-send me-1"></i>Enviar informação</button>
            </form>
        </div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header"><h2 class="card-title">Condições do pedido</h2></div>
            <div class="card-body">
                <dl class="dl-grid">
                    <dt>Montante solicitado</dt><dd>@money($application->requested_amount)</dd>
                    @if ($application->approved_amount)<dt>Montante aprovado</dt><dd class="text-success fw-bold">@money($application->approved_amount) em {{ $application->approved_term }} prestações</dd>@endif
                    <dt>Prestações</dt><dd>{{ $application->term }} ({{ $application->repayment_frequency->label() }})</dd>
                    <dt>Método</dt><dd>{{ $application->amortization_method->label() }}</dd>
                    <dt>Taxa (na submissão)</dt><dd>{{ $application->productVersion->rateLabel() }}</dd>
                    <dt>Prestação simulada</dt><dd>@money($application->simulated_installment)</dd>
                    <dt>Total simulado</dt><dd>@money($application->simulated_total)</dd>
                    <dt>Finalidade</dt><dd>{{ $application->purpose }}</dd>
                    <dt>Rendimento declarado</dt><dd>@money($application->totalIncome())</dd>
                    <dt>Submetido em</dt><dd>@datetime($application->submitted_at)</dd>
                </dl>
                @if ($application->status->value === 'rejected')
                    <div class="alert alert-danger mt-3 mb-0"><strong>Motivo da decisão:</strong> {{ $application->rejection_reason }}</div>
                @endif
                @if ($application->loan)
                    <div class="alert alert-success mt-3 mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="bi bi-check-circle me-1"></i>Empréstimo {{ $application->loan->loan_number }} — {{ $application->loan->status->label() }}</span>
                        <a href="{{ route('client.loans.show', $application->loan) }}" class="btn btn-sm btn-success">Ver empréstimo e contrato</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h2 class="card-title">Documentos associados</h2></div>
            <ul class="list-group list-group-flush">
                @forelse ($application->documents as $doc)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $doc->typeLabel() }} <span class="small text-muted">— {{ \Illuminate\Support\Str::limit($doc->original_name, 30) }}</span></span>
                        <span><x-badge :label="$doc->statusLabel()" :color="$doc->statusColor()" /> <a href="{{ route('client.documents.download', $doc) }}" class="ms-2"><i class="bi bi-download"></i></a></span>
                    </li>
                @empty
                    <li class="list-group-item"><x-empty-state icon="folder" title="Sem documentos associados" /></li>
                @endforelse
            </ul>
        </div>

        @if ($application->guarantors->isNotEmpty() || $application->collaterals->isNotEmpty())
            <div class="card">
                <div class="card-header"><h2 class="card-title">Fiadores e garantias</h2></div>
                <div class="card-body small">
                    @foreach ($application->guarantors as $g)<div class="mb-1"><i class="bi bi-person me-1"></i>{{ $g->full_name }} — {{ $g->phone }} @if ($g->relationship)({{ $g->relationship }})@endif</div>@endforeach
                    @foreach ($application->collaterals as $c)<div class="mb-1"><i class="bi bi-shield me-1"></i>{{ $c->typeLabel() }}: {{ $c->description }} — @money($c->estimated_value)</div>@endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Acompanhamento</h2></div>
            <div class="card-body">
                <ul class="timeline">
                    @foreach ($timeline as $r)
                        <li><span class="dot"></span>
                            <div class="fw-semibold">{{ $r->actionLabel() }}</div>
                            @if (in_array($r->action, ['info_requested', 'rejected', 'cancelled', 'info_provided'], true) && $r->notes)<div class="small">{{ $r->notes }}</div>@endif
                            <div class="meta">@datetime($r->created_at)</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
