{{-- Tabela de prestações. Parâmetros: $installments, $showLoan (opcional) --}}
<div class="table-responsive">
    <table class="table table-hover table-sm align-middle">
        <thead>
        <tr>
            @if ($showLoan ?? false)<th>Empréstimo</th>@endif
            <th>N.º</th><th>Vencimento</th><th class="num">Capital</th><th class="num">Juros</th><th class="num">Encargos</th><th class="num">Penaliz.</th>
            <th class="num">Valor previsto</th><th class="num">Pago</th><th class="num">Pendente</th><th>Estado</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($installments as $i)
            @php($st = $i->displayStatus())
            <tr>
                @if ($showLoan ?? false)<td class="small fw-semibold">{{ $i->loan->loan_number }}</td>@endif
                <td>{{ $i->number }}</td>
                <td>@date($i->due_date) @if ($i->isOverdue())<div class="small text-danger">{{ $i->days_overdue ?: $i->due_date->diffInDays(today()) }} dia(s) de atraso</div>@endif</td>
                <td class="num">{{ \App\Support\Money::format($i->principal_due, false) }}</td>
                <td class="num">{{ \App\Support\Money::format(\App\Support\Money::sub($i->interest_due, $i->interest_waived), false) }}@if (\App\Support\Money::isPositive($i->interest_waived))<i class="bi bi-info-circle text-muted ms-1" data-bs-toggle="tooltip" title="Juros dispensados: {{ \App\Support\Money::format($i->interest_waived) }}"></i>@endif</td>
                <td class="num">{{ \App\Support\Money::format($i->fee_due, false) }}</td>
                <td class="num {{ \App\Support\Money::isPositive($i->penalty_due) ? 'text-danger' : '' }}">{{ \App\Support\Money::format(\App\Support\Money::sub($i->penalty_due, $i->penalty_waived), false) }}</td>
                <td class="num fw-semibold">{{ \App\Support\Money::format($i->total_due, false) }}</td>
                <td class="num">{{ \App\Support\Money::format($i->total_paid, false) }}</td>
                <td class="num">{{ \App\Support\Money::format($i->status->value === 'restructured' ? '0' : $i->outstandingTotal(), false) }}</td>
                <td><x-badge :label="$st['label']" :color="$st['color']" /></td>
            </tr>
        @empty
            <tr><td colspan="11"><x-empty-state icon="calendar3" title="Sem prestações" text="O plano de prestações é gerado na data do desembolso." /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
