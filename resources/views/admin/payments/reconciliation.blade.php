@extends('layouts.admin')
@section('title', 'Reconciliação')

@section('content')
<x-page-header title="Reconciliação" subtitle="Compare os pagamentos confirmados com os extractos dos prestadores e bancos; reconcilie empréstimos individualmente na respectiva ficha." :breadcrumbs="['Pagamentos' => route('admin.payments.index'), 'Reconciliação' => null]" />

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Nova reconciliação por método</h2></div><div class="card-body">
            <form method="POST" action="{{ route('admin.reconciliation.store') }}">
                @csrf
                <x-select name="method" label="Método" :options="\App\Enums\PaymentMethod::options()" required />
                <div class="row">
                    <x-input name="period_start" type="date" label="De" :value="today()->startOfMonth()->toDateString()" required col="col-6 mb-3" />
                    <x-input name="period_end" type="date" label="Até" :value="today()->toDateString()" required col="col-6 mb-3" />
                </div>
                <x-input name="declared_amount" type="number" step="0.01" min="0" label="Total do extracto (MZN)" required />
                <x-textarea name="notes" label="Observações / n.º do extracto" rows="2" />
                <button class="btn btn-success w-100">Reconciliar</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-header"><h2 class="card-title">Histórico de reconciliações</h2></div>
            <div class="table-responsive"><table class="table table-sm table-hover">
                <thead><tr><th>Data</th><th>Âmbito</th><th class="num">Sistema</th><th class="num">Comparado</th><th class="num">Diferença</th><th>Resultado</th><th>Por</th></tr></thead>
                <tbody>
                @forelse ($records as $r)
                    <tr>
                        <td class="small">@datetime($r->created_at)</td>
                        <td class="small">
                            @if ($r->type === 'loan')Empréstimo <a href="{{ route('admin.loans.show', $r->loan_id) }}">{{ $r->loan?->loan_number }}</a>
                            @else{{ \App\Enums\PaymentMethod::from($r->method)->label() }} · @date($r->period_start)–@date($r->period_end) ({{ $r->details['payments_count'] ?? 0 }} pag.)@endif
                            @if ($r->notes)<div class="text-muted">{{ $r->notes }}</div>@endif
                        </td>
                        <td class="num">@money($r->expected_amount)</td><td class="num">@money($r->actual_amount)</td>
                        <td class="num {{ \App\Support\Money::isZero($r->difference) ? '' : 'text-danger fw-bold' }}">@money($r->difference)</td>
                        <td><x-badge :label="$r->status === 'matched' ? 'Sem diferenças' : 'Com diferenças'" :color="$r->status === 'matched' ? 'success' : 'danger'" /></td>
                        <td class="small">{{ $r->performer?->name ?? 'Sistema' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="arrow-left-right" title="Sem reconciliações" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="mt-3">{{ $records->links() }}</div>
    </div>
</div>
@endsection
