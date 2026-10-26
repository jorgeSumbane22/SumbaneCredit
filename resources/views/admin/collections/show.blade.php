@extends('layouts.admin')
@section('title', 'Cobrança '.$case->loan->loan_number)
@php $client = $case->loan->client; @endphp

@section('content')
<x-page-header :title="'Cobrança — '.$case->loan->loan_number" :subtitle="$client->full_name.' · '.$case->days_past_due.' dias de atraso · '.\App\Support\Money::format($case->overdue_amount).' em atraso'" :breadcrumbs="['Cobranças' => route('admin.collections.index'), $case->loan->loan_number => null]">
    <x-badge :label="$case->statusLabel()" :color="$case->statusColor()" class="fs-6" />
    <a href="{{ route('admin.loans.show', $case->loan) }}" class="btn btn-outline-primary">Empréstimo</a>
    @can('payments.register')<a href="{{ route('admin.payments.create', ['emprestimo' => $case->loan_id]) }}" class="btn btn-success">Registar pagamento</a>@endcan
</x-page-header>

<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>As acções de cobrança devem respeitar a dignidade do cliente e as regras aplicáveis às práticas de cobrança. Registe todos os contactos.</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header"><h2 class="card-title">Contactos do cliente</h2></div><div class="card-body small">
            <div><i class="bi bi-telephone me-1"></i>{{ $client->phone }} {{ $client->phone_alt ? '/ '.$client->phone_alt : '' }}</div>
            @if ($client->email)<div><i class="bi bi-envelope me-1"></i>{{ $client->email }}</div>@endif
            <div><i class="bi bi-geo-alt me-1"></i>{{ $client->address?->oneLine() ?: '—' }}</div>
            @if ($client->emergency_contact_name)<div class="mt-2"><strong>Emergência:</strong> {{ $client->emergency_contact_name }} ({{ $client->emergency_contact_relationship }}) {{ $client->emergency_contact_phone }}</div>@endif
            @foreach ($case->loan->guarantors as $g)<div class="mt-1"><strong>Fiador:</strong> {{ $g->full_name }} — {{ $g->phone }}</div>@endforeach
        </div></div>

        @can('collections.manage')
        <div class="card mb-3"><div class="card-header"><h2 class="card-title">Responsável</h2></div><div class="card-body">
            <form method="POST" action="{{ route('admin.collections.assign', $case) }}" class="d-flex gap-2">@csrf
                <select name="user_id" class="form-select form-select-sm"><option value="">— Sem responsável —</option>@foreach ($agents as $a)<option value="{{ $a->id }}" @selected($case->assigned_to === $a->id)>{{ $a->name }}</option>@endforeach</select>
                <button class="btn btn-sm btn-outline-primary">Guardar</button>
            </form>
        </div></div>
        @endcan

        <div class="card"><div class="card-header"><h2 class="card-title">Prestações vencidas</h2></div>
            <ul class="list-group list-group-flush small">
                @forelse ($overdue as $i)
                    <li class="list-group-item d-flex justify-content-between"><span>N.º {{ $i->number }} · @date($i->due_date)<br><span class="text-danger">{{ $i->due_date->diffInDays(today()) }} dias</span></span><strong>@money($i->outstandingTotal())</strong></li>
                @empty
                    <li class="list-group-item text-success">Sem prestações vencidas.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        @can('collections.manage')
        @if (in_array($case->status, ['open', 'promised'], true))
        <div class="card mb-4"><div class="card-header"><h2 class="card-title">Registar contacto</h2></div><div class="card-body">
            <form method="POST" action="{{ route('admin.collections.contact', $case) }}">@csrf
                <div class="row">
                    <x-select name="channel" label="Canal" :options="\App\Models\CollectionContact::CHANNELS" required col="col-md-4 mb-3" />
                    <x-select name="outcome" label="Resultado" :options="\App\Models\CollectionContact::OUTCOMES" required col="col-md-4 mb-3" />
                    <x-input name="contacted_at" type="datetime-local" label="Data/hora" :value="now()->format('Y-m-d\TH:i')" required col="col-md-4 mb-3" />
                </div>
                <div class="row" data-show-when="outcome=promise">
                    <x-input name="promised_amount" type="number" step="0.01" min="1" label="Valor prometido (MZN)" col="col-md-6 mb-3" />
                    <x-input name="promised_date" type="date" label="Data prometida" :min="today()->toDateString()" col="col-md-6 mb-3" />
                </div>
                <x-textarea name="notes" label="Notas" rows="2" />
                <div class="row align-items-end">
                    <x-input name="next_action_at" type="datetime-local" label="Próxima acção" col="col-md-6 mb-3" />
                    <div class="col-md-6 mb-3 text-end"><button class="btn btn-success">Registar contacto</button></div>
                </div>
            </form>
        </div></div>
        @endif
        @endcan

        <div class="card mb-4"><div class="card-header"><h2 class="card-title">Promessas de pagamento</h2></div>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>Data prometida</th><th class="num">Valor</th><th>Estado</th><th>Registada por</th><th></th></tr></thead>
                <tbody>
                @forelse ($case->promises as $pr)
                    <tr><td>@date($pr->promised_date)</td><td class="num">@money($pr->promised_amount)</td><td><x-badge :label="$pr->statusLabel()" :color="$pr->statusColor()" /></td><td class="small">{{ $pr->creator?->name }}</td>
                        <td class="text-end">@can('collections.manage')@if ($pr->status === 'pending')<x-action-form :action="route('admin.promises.cancel', $pr)" confirm="Cancelar a promessa?" button="Cancelar" class="btn btn-sm btn-link text-danger" />@endif @endcan</td></tr>
                @empty
                    <tr><td colspan="5" class="text-muted small p-3">Sem promessas.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card mb-4"><div class="card-header"><h2 class="card-title">Histórico de contactos</h2></div><div class="card-body">
            <ul class="timeline">
                @forelse ($case->contacts as $ct)
                    <li><span class="dot"></span>
                        <div class="fw-semibold small">{{ \App\Models\CollectionContact::CHANNELS[$ct->channel] }} — {{ \App\Models\CollectionContact::OUTCOMES[$ct->outcome] }}</div>
                        @if ($ct->notes)<div class="small">{{ $ct->notes }}</div>@endif
                        <div class="meta">@datetime($ct->contacted_at) · {{ $ct->user?->name }}</div>
                    </li>
                @empty
                    <x-empty-state icon="telephone" title="Sem contactos registados" />
                @endforelse
            </ul>
        </div></div>

        @can('collections.manage')
            @if (in_array($case->status, ['open', 'promised'], true))
                <x-action-form :action="route('admin.collections.close', $case)" confirm="Encerrar o processo de cobrança?" reason="reason" button="Encerrar processo" class="btn btn-outline-secondary" />
            @endif
        @endcan
    </div>
</div>
@endsection
