@extends('layouts.admin')
@section('title', 'Pedido '.$application->application_number)
@php use App\Support\Money; $a = $application; @endphp

@section('content')
<x-page-header :title="'Pedido '.$a->application_number" :subtitle="$a->client->full_name.' · '.$a->product->name.' (condições v'.$a->productVersion->version.')'" :breadcrumbs="['Pedidos' => route('admin.applications.index'), $a->application_number => null]">
    <x-badge :status="$a->status" class="fs-6" />
    @if ($a->loan)<a href="{{ route('admin.loans.show', $a->loan) }}" class="btn btn-success"><i class="bi bi-briefcase me-1"></i>Empréstimo {{ $a->loan->loan_number }}</a>@endif
</x-page-header>

@if ($a->status->isOpen())
<div class="card mb-4"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
    <span class="small-label me-2">Acções</span>
    @can('review', $a)
        @if ($a->status->value === 'submitted')
            <x-action-form :action="route('admin.applications.start-review', $a)" button="Iniciar análise" icon="play-circle" class="btn btn-sm btn-primary" />
        @endif
        <x-action-form :action="route('admin.applications.assess', $a)" button="Executar avaliação" icon="speedometer" class="btn btn-sm btn-outline-primary" confirm="Executar a avaliação semiautomática com as regras em vigor?" />
        @if ($a->status->value !== 'pending_documents')
            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#mInfo"><i class="bi bi-question-circle me-1"></i>Pedir informação</button>
        @endif
        @if (in_array($a->status->value, ['submitted', 'under_review'], true))
            <x-action-form :action="route('admin.applications.forward', $a)" button="Encaminhar para decisão" icon="forward" class="btn btn-sm btn-outline-primary" confirm="Encaminhar o pedido para decisão do gestor?" />
        @endif
    @endcan
    @can('decide', $a)
        @if (in_array($a->status->value, ['under_review', 'forwarded'], true))
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#mApprove"><i class="bi bi-check-circle me-1"></i>Aprovar</button>
        @endif
        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#mReject"><i class="bi bi-x-circle me-1"></i>Rejeitar</button>
    @endcan
    @can('cancel', $a)
        <x-action-form :action="route('admin.applications.cancel', $a)" confirm="Cancelar o pedido?" reason="reason" button="Cancelar" class="btn btn-sm btn-outline-secondary" />
    @endcan
    @can('applications.review')
        <form method="POST" action="{{ route('admin.applications.assign', $a) }}" class="ms-auto d-flex gap-1">
            @csrf
            <select name="user_id" class="form-select form-select-sm" style="width:auto">
                @foreach ($analysts as $u)<option value="{{ $u->id }}" @selected($a->assigned_to === $u->id)>{{ $u->name }}</option>@endforeach
            </select>
            <button class="btn btn-sm btn-outline-secondary">Atribuir</button>
        </form>
    @endcan
</div></div>
@endif

<div class="row g-4">
    <div class="col-xl-8">
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100"><div class="card-header"><h2 class="card-title">Pedido</h2></div><div class="card-body">
                    <dl class="dl-grid small">
                        <dt>Montante</dt><dd class="fw-bold">@money($a->requested_amount)</dd>
                        <dt>Prestações</dt><dd>{{ $a->term }} · {{ $a->repayment_frequency->label() }}</dd>
                        <dt>Método</dt><dd>{{ $a->amortization_method->shortLabel() }}</dd>
                        <dt>Taxa</dt><dd>{{ $a->productVersion->rateLabel() }}</dd>
                        <dt>Prestação simulada</dt><dd>@money($a->simulated_installment)</dd>
                        <dt>Total simulado</dt><dd>@money($a->simulated_total)</dd>
                        <dt>Finalidade</dt><dd>{{ $a->purpose }}</dd>
                        <dt>Submetido</dt><dd>@datetime($a->submitted_at)</dd>
                        <dt>Responsável</dt><dd>{{ $a->assignee?->name ?? '—' }}</dd>
                        @if ($a->decided_at)<dt>Decisão</dt><dd>{{ $a->decider?->name }} em @datetime($a->decided_at)</dd>@endif
                        @if ($a->approved_amount)<dt>Aprovado</dt><dd class="text-success fw-bold">@money($a->approved_amount) · {{ $a->approved_term }} prest.</dd>@endif
                        @if ($a->rejection_reason)<dt>Motivo</dt><dd class="text-danger">{{ $a->rejection_reason }}</dd>@endif
                    </dl>
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card h-100"><div class="card-header"><h2 class="card-title">Análise financeira (declarada)</h2></div><div class="card-body">
                    <dl class="dl-grid small">
                        <dt>Rendimento mensal</dt><dd>@money($a->monthly_income)</dd>
                        <dt>Outros rendimentos</dt><dd>@money($a->other_income)</dd>
                        <dt>Despesas mensais</dt><dd>@money($a->monthly_expenses)</dd>
                        <dt>Outras prestações</dt><dd>@money($a->existing_obligations)</dd>
                        <dt>Rendimento disponível</dt><dd class="fw-bold {{ Money::lt($disposable, '0') ? 'text-danger' : '' }}">@money($disposable)</dd>
                        <dt>Situação profissional</dt><dd>{{ \App\Models\Client::EMPLOYMENT_TYPES[$a->employment_type] ?? '—' }}</dd>
                        <dt>Entidade</dt><dd>{{ $a->employer_name ?? '—' }}</dd>
                        <dt>Profissão</dt><dd>{{ $a->occupation ?? '—' }}</dd>
                    </dl>
                    <a href="{{ route('admin.clients.show', $a->client) }}" class="small">Ficha completa do cliente →</a>
                </div></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between"><h2 class="card-title">Avaliação de crédito (apoio à decisão)</h2>
                @if ($latest)<x-badge :label="$latest->resultLabel()" :color="\App\Models\CreditAssessment::colorFor($latest->result)" class="fs-6" />@endif</div>
            <div class="card-body">
                @if ($latest)
                    <p class="small text-muted">Executada por {{ $latest->performer?->name }} em @datetime($latest->created_at) com as regras v{{ $latest->rules_version }}.</p>
                    @foreach ($latest->indicators as $ind)
                        <div class="indicator {{ $ind['status'] }}">
                            <div class="d-flex justify-content-between flex-wrap"><strong class="small">{{ $ind['label'] }}</strong><span class="small fw-semibold">{{ $ind['value'] }} <span class="text-muted fw-normal">({{ $ind['threshold'] }})</span></span></div>
                            <div class="small text-muted">{{ $ind['explanation'] }}</div>
                        </div>
                    @endforeach
                    <div class="alert alert-light border small mt-3 mb-0">{{ $latest->explanation }}</div>
                    @if ($a->assessments->count() > 1)
                        <details class="mt-2 small"><summary>Avaliações anteriores ({{ $a->assessments->count() - 1 }})</summary>
                            <ul>@foreach ($a->assessments->slice(1) as $old)<li>@datetime($old->created_at) — {{ $old->resultLabel() }} (regras v{{ $old->rules_version }})</li>@endforeach</ul>
                        </details>
                    @endif
                @else
                    <x-empty-state icon="speedometer" title="Avaliação ainda não executada" text="É obrigatória antes do encaminhamento e da decisão." />
                @endif
            </div>
        </div>

        <div class="card mb-4"><div class="card-header"><h2 class="card-title">Documentos</h2></div>
            <div class="table-responsive"><table class="table table-sm">
                <tbody>
                @forelse ($a->documents as $doc)
                    <tr>
                        <td>{{ $doc->typeLabel() }}<div class="small text-muted">{{ \Illuminate\Support\Str::limit($doc->original_name, 40) }}</div></td>
                        <td><x-badge :label="$doc->statusLabel()" :color="$doc->statusColor()" /></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.documents.download', $doc) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>
                            @can('verify', $doc)
                                @if ($doc->status !== 'verified')<x-action-form :action="route('admin.documents.review', $doc)" button="Verificar" class="btn btn-sm btn-success"><input type="hidden" name="status" value="verified"></x-action-form>@endif
                                @if ($doc->status !== 'rejected')<x-action-form :action="route('admin.documents.review', $doc)" confirm="Rejeitar o documento?" reason="reason" button="Rejeitar" class="btn btn-sm btn-outline-danger"><input type="hidden" name="status" value="rejected"></x-action-form>@endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td><x-empty-state icon="folder" title="Sem documentos associados" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        @if ($a->guarantors->isNotEmpty() || $a->collaterals->isNotEmpty())
        <div class="card"><div class="card-header"><h2 class="card-title">Fiadores e garantias</h2></div>
            <div class="table-responsive"><table class="table table-sm">
                <tbody>
                @foreach ($a->guarantors as $g)
                    <tr><td><i class="bi bi-person me-1"></i>{{ $g->full_name }}<div class="small text-muted">{{ $g->document_number }} · {{ $g->phone }} · {{ $g->relationship }} · @money($g->monthly_income)</div></td>
                        <td>
                            <form method="POST" action="{{ route('admin.guarantors.status', $g) }}" class="d-flex gap-1">@csrf
                                <select name="status" class="form-select form-select-sm">@foreach (['declared' => 'Declarado', 'verified' => 'Verificado', 'rejected' => 'Rejeitado'] as $k => $v)<option value="{{ $k }}" @selected($g->status === $k)>{{ $v }}</option>@endforeach</select>
                                @can('applications.review')<button class="btn btn-sm btn-outline-secondary">OK</button>@endcan
                            </form>
                        </td></tr>
                @endforeach
                @foreach ($a->collaterals as $c)
                    <tr><td><i class="bi bi-shield me-1"></i>{{ $c->typeLabel() }}: {{ $c->description }}<div class="small text-muted">Valor estimado @money($c->estimated_value)</div></td>
                        <td>
                            <form method="POST" action="{{ route('admin.collaterals.status', $c) }}" class="d-flex gap-1">@csrf
                                <select name="status" class="form-select form-select-sm">@foreach (\App\Models\Collateral::STATUSES as $k => $v)<option value="{{ $k }}" @selected($c->status === $k)>{{ $v }}</option>@endforeach</select>
                                @can('applications.review')<button class="btn btn-sm btn-outline-secondary">OK</button>@endcan
                            </form>
                        </td></tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        @endif
    </div>

    <div class="col-xl-4">
        @if ($a->status->value === 'pending_documents')
            <div class="alert alert-warning"><strong>Informação pedida ao cliente:</strong><br>{{ $a->info_request }}</div>
        @endif
        <div class="card mb-4"><div class="card-header"><h2 class="card-title">Cliente</h2></div><div class="card-body small">
            <div class="fw-bold">{{ $a->client->full_name }}</div>
            <div>{{ $a->client->documentTypeLabel() }} {{ $a->client->document_number }} · NUIT {{ $a->client->nuit ?? '—' }}</div>
            <div>{{ $a->client->phone }} · {{ $a->client->address?->province }}</div>
            <div class="mt-2">Identificação: <x-badge :label="\App\Models\Client::KYC_STATUSES[$a->client->kyc_status]" :color="['pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'][$a->client->kyc_status]" /></div>
            <div>Empréstimos anteriores: {{ $a->client->loans->where('id', '!=', $a->loan?->id)->count() }}</div>
        </div></div>

        @can('applications.review')
        <div class="card mb-4"><div class="card-header"><h2 class="card-title">Registar observação</h2></div><div class="card-body">
            <form method="POST" action="{{ route('admin.applications.note', $a) }}">@csrf
                <x-textarea name="notes" rows="3" required placeholder="Observação interna (não visível ao cliente)" col="mb-2" />
                <button class="btn btn-sm btn-primary">Guardar</button>
            </form>
        </div></div>
        @endcan

        <div class="card"><div class="card-header"><h2 class="card-title">Histórico do pedido</h2></div><div class="card-body">
            <ul class="timeline">
                @foreach ($a->reviews as $r)
                    <li><span class="dot"></span>
                        <div class="fw-semibold small">{{ $r->actionLabel() }} @if ($r->to_status && $r->from_status)<span class="text-muted fw-normal">({{ \App\Enums\ApplicationStatus::from($r->from_status)->label() }} → {{ \App\Enums\ApplicationStatus::from($r->to_status)->label() }})</span>@endif</div>
                        @if ($r->notes)<div class="small">{{ $r->notes }}</div>@endif
                        <div class="meta">@datetime($r->created_at) · {{ $r->user?->name ?? 'Sistema' }}</div>
                    </li>
                @endforeach
            </ul>
        </div></div>
    </div>
</div>

{{-- Modais --}}
<div class="modal fade" id="mInfo" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.applications.request-info', $a) }}">@csrf
    <div class="modal-header"><h5 class="modal-title">Pedir informação ou documentos</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><x-textarea name="message" label="Mensagem ao cliente" rows="4" required placeholder="Ex.: Envie o extracto bancário dos últimos 3 meses." /></div>
    <div class="modal-footer"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Fechar</button><button class="btn btn-warning">Enviar pedido</button></div>
</form></div></div>

<div class="modal fade" id="mApprove" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.applications.approve', $a) }}" data-confirm="Confirma a aprovação deste pedido? Será criado o empréstimo e notificado o cliente.">@csrf
    <div class="modal-header"><h5 class="modal-title">Aprovar pedido</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        @if ($latest?->result === 'unfavorable')
            <div class="alert alert-danger small">A avaliação mais recente é <strong>desfavorável</strong>. A aprovação exige confirmação expressa e justificação.</div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_unfavorable" value="1" id="cu"><label class="form-check-label small" for="cu">Confirmo a aprovação contra a recomendação automática.</label></div>
        @elseif (! $latest)
            <div class="alert alert-warning small">Ainda não foi executada a avaliação de crédito (obrigatória).</div>
        @endif
        <div class="row">
            <x-input name="approved_amount" type="number" step="0.01" label="Montante aprovado (MZN)" :value="$a->requested_amount" required col="col-6 mb-3" :max="$a->requested_amount" />
            <x-input name="approved_term" type="number" label="Prestações" :value="$a->term" required col="col-6 mb-3" :min="$a->productVersion->min_term" :max="$a->productVersion->max_term" />
        </div>
        <x-textarea name="decision_notes" label="Fundamentação da decisão" rows="3" />
        <p class="small text-muted mb-0">A decisão é registada com o seu nome. O empréstimo fica a aguardar formalização do contrato; o desembolso só ocorre após a formalização.</p>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Fechar</button><button class="btn btn-success">Aprovar</button></div>
</form></div></div>

<div class="modal fade" id="mReject" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.applications.reject', $a) }}" data-confirm="Confirma a rejeição? O cliente será notificado com o motivo.">@csrf
    <div class="modal-header"><h5 class="modal-title">Rejeitar pedido</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><x-textarea name="reason" label="Motivo (comunicado ao cliente)" rows="4" required /></div>
    <div class="modal-footer"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Fechar</button><button class="btn btn-danger">Rejeitar</button></div>
</form></div></div>
@endsection
