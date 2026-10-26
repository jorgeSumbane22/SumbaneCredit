@extends('layouts.admin')
@section('title', $client->full_name)
@php use App\Support\Money; @endphp

@section('content')
<x-page-header :title="$client->full_name" :subtitle="$client->client_number.' · '.$client->documentTypeLabel().' '.$client->document_number" :breadcrumbs="['Clientes' => route('admin.clients.index'), $client->client_number => null]">
    <x-badge :label="$client->statusLabel()" :color="['active' => 'success', 'inactive' => 'secondary', 'blocked' => 'danger'][$client->status]" class="fs-6" />
    @can('applications.review')
        <a href="{{ route('admin.clients.applications.create', $client) }}" class="btn btn-success"><i class="bi bi-file-earmark-plus me-1"></i>Novo pedido</a>
    @endcan
    @can('clients.update')
        <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    @endcan
</x-page-header>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><x-stat-card label="Em dívida" :value="Money::format($summary['outstanding'])" icon="wallet2" color="orange" :hint="'Capital: '.Money::format($summary['principal'])" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Total pago" :value="Money::format($summary['paid'])" icon="cash-stack" color="green" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Empréstimos" :value="$client->loans->count()" icon="briefcase" :hint="$client->loans->where('status.value', 'active')->count().' activo(s)'" /></div>
    <div class="col-6 col-lg-3"><x-stat-card label="Maior atraso actual" :value="$summary['max_dpd'].' dias'" icon="exclamation-triangle" :color="$summary['max_dpd'] > 0 ? 'red' : 'green'" /></div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    @foreach (['dados' => 'Dados', 'documentos' => 'Documentos ('.$client->documents->count().')', 'credito' => 'Pedidos e empréstimos', 'pagamentos' => 'Pagamentos', 'historico' => 'Histórico'] as $id => $label)
        <li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $id }}" type="button">{{ $label }}</button></li>
    @endforeach
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-dados">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card"><div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="dl-grid small">
                                <dt>Data de nascimento</dt><dd>@date($client->birth_date) @if ($client->birth_date)({{ $client->birth_date->age }} anos)@endif</dd>
                                <dt>Nacionalidade</dt><dd>{{ $client->nationality ?? '—' }}</dd>
                                <dt>NUIT</dt><dd>{{ $client->nuit ?? '—' }}</dd>
                                <dt>Validade do documento</dt><dd>@date($client->document_expiry)</dd>
                                <dt>Telemóvel</dt><dd>{{ $client->phone }} {{ $client->phone_alt ? '/ '.$client->phone_alt : '' }}</dd>
                                <dt>Email</dt><dd>{{ $client->email ?? '—' }}</dd>
                                <dt>Morada</dt><dd>{{ $client->address?->oneLine() ?: '—' }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="dl-grid small">
                                <dt>Situação</dt><dd>{{ $client->employment?->employmentTypeLabel() ?? '—' }}</dd>
                                <dt>Profissão</dt><dd>{{ $client->occupation ?? '—' }}</dd>
                                <dt>Entidade</dt><dd>{{ $client->employment?->employer_name ?? '—' }}</dd>
                                <dt>Rendimento</dt><dd>@money($client->employment?->monthly_income ?? $client->monthly_income)</dd>
                                <dt>Outros rendimentos</dt><dd>@money($client->employment?->other_income)</dd>
                                <dt>Despesas</dt><dd>@money($client->employment?->monthly_expenses)</dd>
                                <dt>Contacto de emergência</dt><dd>{{ $client->emergency_contact_name ?? '—' }} {{ $client->emergency_contact_phone }}</dd>
                            </dl>
                        </div>
                    </div>
                    @if ($client->notes)<div class="alert alert-light border small mb-0"><strong>Observações:</strong> {{ $client->notes }}</div>@endif
                </div></div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-3"><div class="card-header"><h2 class="card-title">Identificação (KYC)</h2></div><div class="card-body">
                    <x-badge :label="\App\Models\Client::KYC_STATUSES[$client->kyc_status]" :color="['pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'][$client->kyc_status]" />
                    @if ($client->kyc_verified_at)<div class="small text-muted mt-1">@datetime($client->kyc_verified_at)</div>@endif
                    @can('clients.documents.verify')
                        <div class="d-flex gap-2 mt-3">
                            <x-action-form :action="route('admin.clients.kyc', $client)" confirm="Confirma que verificou presencialmente/documentalmente a identidade do cliente?" button="Verificar" icon="check2" class="btn btn-sm btn-success"><input type="hidden" name="kyc_status" value="verified"></x-action-form>
                            <x-action-form :action="route('admin.clients.kyc', $client)" confirm="Rejeitar a identificação do cliente?" reason="reason" button="Rejeitar" class="btn btn-sm btn-outline-danger" confirm-color="#DC3545"><input type="hidden" name="kyc_status" value="rejected"></x-action-form>
                        </div>
                    @endcan
                </div></div>
                <div class="card mb-3"><div class="card-header"><h2 class="card-title">Conta</h2></div><div class="card-body small">
                    @if ($client->user)
                        <div>Acesso ao portal: <strong>{{ $client->user->email }}</strong></div>
                        <div>Email verificado: {{ $client->user->email_verified_at ? 'sim' : 'não' }}</div>
                        <div>Último acesso: @datetime($client->user->last_login_at)</div>
                    @else
                        <div class="text-muted">Cliente sem acesso ao portal.</div>
                    @endif
                    @can('clients.update')
                        <form method="POST" action="{{ route('admin.clients.status', $client) }}" class="mt-3" data-confirm="Alterar o estado da conta do cliente?" data-reason="reason" data-reason-label="Motivo">
                            @csrf
                            <div class="input-group input-group-sm">
                                <select name="status" class="form-select">@foreach (\App\Models\Client::STATUSES as $k => $v)<option value="{{ $k }}" @selected($client->status === $k)>{{ $v }}</option>@endforeach</select>
                                <button class="btn btn-outline-primary" type="submit">Alterar estado</button>
                            </div>
                        </form>
                    @endcan
                </div></div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-documentos">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card"><div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Documento</th><th>Carregado</th><th>Estado</th><th class="text-end">Acções</th></tr></thead>
                        <tbody>
                        @forelse ($client->documents as $doc)
                            <tr>
                                <td><div class="fw-semibold">{{ $doc->typeLabel() }}</div><div class="small text-muted">{{ \Illuminate\Support\Str::limit($doc->original_name, 40) }} · {{ $doc->humanSize() }}</div></td>
                                <td class="small">@datetime($doc->created_at)</td>
                                <td><x-badge :label="$doc->statusLabel()" :color="$doc->statusColor()" />
                                    @if ($doc->verifier)<div class="small text-muted">{{ $doc->verifier->name }}</div>@endif
                                    @if ($doc->rejection_reason)<div class="small text-danger">{{ $doc->rejection_reason }}</div>@endif</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('admin.documents.download', $doc) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>
                                    @can('verify', $doc)
                                        @if ($doc->status !== 'verified')
                                            <x-action-form :action="route('admin.documents.review', $doc)" button="Verificar" class="btn btn-sm btn-success"><input type="hidden" name="status" value="verified"></x-action-form>
                                        @endif
                                        @if ($doc->status !== 'rejected')
                                            <x-action-form :action="route('admin.documents.review', $doc)" confirm="Rejeitar o documento?" reason="reason" reason-label="Motivo da rejeição (visível ao cliente)" button="Rejeitar" class="btn btn-sm btn-outline-danger" confirm-color="#DC3545"><input type="hidden" name="status" value="rejected"></x-action-form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-empty-state icon="folder" title="Sem documentos" /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div></div>
            </div>
            @can('clients.update')
            <div class="col-lg-4">
                <div class="card"><div class="card-header"><h2 class="card-title">Carregar documento</h2></div><div class="card-body">
                    <form method="POST" action="{{ route('admin.clients.documents.store', $client) }}" enctype="multipart/form-data">
                        @csrf
                        <x-select name="type" label="Tipo" :options="\App\Models\ClientDocument::TYPES" required />
                        <x-input name="file" type="file" label="Ficheiro" required accept=".pdf,.jpg,.jpeg,.png,.webp" />
                        <x-input name="notes" label="Observações" />
                        <button class="btn btn-success w-100">Carregar</button>
                    </form>
                </div></div>
            </div>
            @endcan
        </div>
    </div>

    <div class="tab-pane fade" id="tab-credito">
        <div class="card mb-4"><div class="card-header"><h2 class="card-title">Empréstimos</h2></div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>N.º</th><th>Produto</th><th class="num">Capital</th><th class="num">Em dívida</th><th>Atraso</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse ($client->loans as $l)
                    <tr><td><a href="{{ route('admin.loans.show', $l) }}" class="fw-semibold">{{ $l->loan_number }}</a></td><td>{{ $l->product->name }}</td>
                        <td class="num">@money($l->principal_amount)</td><td class="num">@money($l->total_outstanding)</td>
                        <td>@if ($l->days_past_due)<span class="badge badge-soft-danger">{{ $l->days_past_due }} d</span>@else — @endif</td><td><x-badge :status="$l->status" /></td></tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="briefcase" title="Sem empréstimos" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card"><div class="card-header"><h2 class="card-title">Pedidos</h2></div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>N.º</th><th>Produto</th><th class="num">Montante</th><th>Submetido</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse ($client->applications as $a)
                    <tr><td><a href="{{ route('admin.applications.show', $a) }}" class="fw-semibold">{{ $a->application_number }}</a></td><td>{{ $a->product->name }}</td>
                        <td class="num">@money($a->requested_amount)</td><td class="small">@date($a->submitted_at)</td><td><x-badge :status="$a->status" /></td></tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="file-earmark" title="Sem pedidos" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-pagamentos">
        <div class="card"><div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>N.º</th><th>Data</th><th>Empréstimo</th><th>Método</th><th class="num">Valor</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse ($payments as $p)
                <tr><td><a href="{{ route('admin.payments.show', $p) }}">{{ $p->payment_number }}</a></td><td>@date($p->paid_at)</td><td>{{ $p->loan->loan_number }}</td><td>{{ $p->method->label() }}</td><td class="num">@money($p->amount)</td><td><x-badge :status="$p->status" /></td></tr>
            @empty
                <tr><td colspan="6"><x-empty-state icon="cash" title="Sem pagamentos" /></td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>

    <div class="tab-pane fade" id="tab-historico">
        <div class="card"><div class="card-body">
            <ul class="timeline">
                @forelse ($history as $h)
                    <li><span class="dot"></span><div class="fw-semibold small">{{ $h->action }}</div><div class="small">{{ $h->description }}</div><div class="meta">@datetime($h->created_at) · {{ $h->user?->name ?? 'Sistema' }}</div></li>
                @empty
                    <x-empty-state icon="clock-history" title="Sem histórico" />
                @endforelse
            </ul>
        </div></div>
    </div>
</div>
@endsection
