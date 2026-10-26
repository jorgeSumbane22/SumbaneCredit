<?php

namespace App\Services\Reports;

use App\Enums\ApplicationStatus;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exports\ArrayExport;
use App\Models\Client;
use App\Models\CollectionCase;
use App\Models\CollectionContact;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationReview;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Relatórios operacionais e financeiros. Cada relatório devolve colunas, linhas (valores
 * brutos), colunas monetárias e resumo — a mesma estrutura serve o ecrã, o PDF e o Excel.
 */
class ReportService
{
    public const TYPES = [
        'clients' => ['Clientes', 'people', 'Registo de clientes no período'],
        'applications' => ['Pedidos de crédito', 'file-earmark-text', 'Pedidos submetidos e decisões'],
        'loans' => ['Empréstimos', 'briefcase', 'Carteira de empréstimos'],
        'disbursements' => ['Desembolsos', 'send', 'Desembolsos efectuados e pendentes'],
        'payments' => ['Pagamentos', 'cash-stack', 'Pagamentos registados'],
        'installments' => ['Prestações', 'calendar3', 'Prestações por data de vencimento'],
        'interest' => ['Juros recebidos', 'percent', 'Juros, encargos e penalizações cobrados'],
        'outstanding' => ['Capital em dívida', 'wallet2', 'Situação actual da carteira por produto'],
        'collections' => ['Cobranças', 'telephone-outbound', 'Processos de cobrança'],
        'default' => ['Incumprimento', 'exclamation-triangle', 'Carteira em risco por escalão de atraso'],
        'recovery' => ['Recuperação', 'arrow-repeat', 'Valores devidos vs. recuperados por mês'],
        'monthly' => ['Desempenho mensal', 'calendar-month', 'Indicadores agregados por mês'],
        'annual' => ['Desempenho anual', 'calendar-range', 'Indicadores agregados por ano'],
        'agents' => ['Actividade dos agentes', 'person-badge', 'Operações registadas por funcionário'],
    ];

    public const ROW_LIMIT = 500;

    public function __construct(private readonly AuditService $audit) {}

    public function build(string $type, Request $request, bool $full = false): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$from, $to] = Period::fromRequest($request, in_array($type, ['annual'], true) ? 'last_year' : 'this_month');
        if ($type === 'annual' && ! $request->filled('periodo') && ! $request->filled('de')) {
            $from = CarbonImmutable::today()->subYears(4)->startOfYear();
            $to = CarbonImmutable::today()->endOfDay();
        }

        $report = $this->{$type}($from, $to, $request) + ['money' => [], 'summary' => [], 'filters' => []];
        $report['key'] = $type;
        $report['title'] = self::TYPES[$type][0];
        $report['from'] = $from;
        $report['to'] = $to;
        $report['period_label'] = Period::label($from, $to);
        $report['total_rows'] = count($report['rows']);
        if (! $full && count($report['rows']) > self::ROW_LIMIT) {
            $report['rows'] = array_slice($report['rows'], 0, self::ROW_LIMIT);
            $report['truncated'] = true;
        }

        return $report;
    }

    /** Versão formatada para apresentação (ecrã/PDF). */
    public function present(array $report): array
    {
        $moneyIdx = array_keys(array_intersect($report['columns'], $report['money']));
        $report['rows'] = array_map(function ($row) use ($moneyIdx) {
            $row = array_values($row);
            foreach ($moneyIdx as $i) {
                $row[$i] = $row[$i] === null || $row[$i] === '' ? '—' : Money::format($row[$i], false);
            }

            return $row;
        }, $report['rows']);
        $report['numeric'] = array_merge($report['money'], $report['numeric'] ?? []);

        return $report;
    }

    public function excel(string $type, Request $request): BinaryFileResponse
    {
        $report = $this->build($type, $request, true);
        $moneyIdx = array_keys(array_intersect($report['columns'], $report['money']));
        $rows = array_map(function ($row) use ($moneyIdx) {
            $row = array_values($row);
            foreach ($moneyIdx as $i) {
                $row[$i] = $row[$i] === null || $row[$i] === '' ? null : (float) Money::toDb($row[$i]);
            }

            return $row;
        }, $report['rows']);
        $this->audit->log('report.exported', null, null, ['type' => $type, 'format' => 'xlsx', 'filters' => $request->query()]);

        return Excel::download(
            new ArrayExport($report['title'], $report['columns'], $rows, ['Período' => $report['period_label']] + $report['summary']),
            'relatorio-'.$type.'-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function between($query, string $column, CarbonImmutable $from, CarbonImmutable $to)
    {
        return $query->whereBetween($column, [$from->startOfDay(), $to->endOfDay()]);
    }

    // ------------------------------------------------------------------ relatórios

    private function clients(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $q = Client::with('address', 'employment')->withCount('loans')
            ->when($r->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->when($r->query('kyc'), fn ($q, $s) => $q->where('kyc_status', $s))
            ->search($r->query('q'));
        if (! $r->boolean('todos')) {
            $this->between($q, 'created_at', $from, $to);
        }
        $items = $q->orderBy('client_number')->get();

        return [
            'columns' => ['N.º', 'Nome', 'Documento', 'NUIT', 'Telefone', 'Email', 'Província', 'Profissão', 'Rendimento', 'Estado', 'KYC', 'Empréstimos', 'Registo'],
            'money' => ['Rendimento'],
            'numeric' => ['Empréstimos'],
            'rows' => $items->map(fn ($c) => [
                $c->client_number, $c->full_name, $c->documentTypeLabel().' '.$c->document_number, $c->nuit, $c->phone, $c->email,
                $c->address?->province, $c->occupation, $c->employment?->monthly_income ?? $c->monthly_income,
                $c->statusLabel(), Client::KYC_STATUSES[$c->kyc_status] ?? $c->kyc_status, $c->loans_count, $c->created_at->format('d/m/Y'),
            ])->all(),
            'summary' => ['Clientes' => $items->count(), 'Identificação verificada' => $items->where('kyc_status', 'verified')->count()],
            'filters' => ['estado' => Client::STATUSES, 'kyc' => Client::KYC_STATUSES],
        ];
    }

    private function applications(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $items = $this->between(LoanApplication::with('client', 'product', 'decider'), 'submitted_at', $from, $to)
            ->when($r->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->when($r->query('produto'), fn ($q, $p) => $q->where('loan_product_id', $p))
            ->orderBy('submitted_at')->get();

        return [
            'columns' => ['N.º', 'Submetido', 'Cliente', 'Produto', 'Montante pedido', 'Prestações', 'Montante aprovado', 'Estado', 'Decisão', 'Decidido por'],
            'money' => ['Montante pedido', 'Montante aprovado'],
            'numeric' => ['Prestações'],
            'rows' => $items->map(fn ($a) => [
                $a->application_number, $a->submitted_at?->format('d/m/Y'), $a->client->full_name, $a->product->name,
                (string) $a->requested_amount, $a->term, $a->approved_amount ? (string) $a->approved_amount : null,
                $a->status->label(), $a->decided_at?->format('d/m/Y'), $a->decider?->name,
            ])->all(),
            'summary' => [
                'Pedidos' => $items->count(),
                'Aprovados' => $items->where('status', ApplicationStatus::Approved)->count(),
                'Rejeitados' => $items->where('status', ApplicationStatus::Rejected)->count(),
                'Montante pedido' => Money::format(Money::sum($items, 'requested_amount')),
                'Montante aprovado' => Money::format(Money::sum($items->where('status', ApplicationStatus::Approved), 'approved_amount')),
            ],
            'filters' => ['estado' => ApplicationStatus::options()],
        ];
    }

    private function loans(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $items = Loan::with('client', 'product', 'officer')
            ->when(! $r->boolean('todos'), fn ($q) => $this->between($q, 'created_at', $from, $to))
            ->when($r->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('loan_number')->get();

        return [
            'columns' => ['N.º', 'Contrato', 'Cliente', 'Produto', 'Capital', 'Taxa', 'Prestações', 'Desembolso', 'Total contratado', 'Pago', 'Em dívida', 'Dias atraso', 'Estado', 'Agente'],
            'money' => ['Capital', 'Total contratado', 'Pago', 'Em dívida'],
            'numeric' => ['Prestações', 'Dias atraso'],
            'rows' => $items->map(fn ($l) => [
                $l->loan_number, $l->contract_number, $l->client->full_name, $l->product->name, (string) $l->principal_amount, $l->rateLabel(),
                $l->term, $l->disbursed_at?->format('d/m/Y'), (string) $l->total_contract_amount, (string) $l->total_paid,
                (string) $l->total_outstanding, $l->days_past_due, $l->status->label(), $l->officer?->name,
            ])->all(),
            'summary' => [
                'Empréstimos' => $items->count(),
                'Capital contratado' => Money::format(Money::sum($items, 'principal_amount')),
                'Em dívida' => Money::format(Money::sum($items, 'total_outstanding')),
            ],
            'filters' => ['estado' => LoanStatus::options()],
        ];
    }

    private function disbursements(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $items = $this->between(Disbursement::with('loan.client', 'requester', 'authorizer', 'executor'), 'requested_at', $from, $to)
            ->when($r->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('requested_at')->get();

        return [
            'columns' => ['N.º', 'Empréstimo', 'Cliente', 'Bruto', 'Encargos deduzidos', 'Líquido', 'Método', 'Referência', 'Data', 'Estado', 'Registado por', 'Autorizado por'],
            'money' => ['Bruto', 'Encargos deduzidos', 'Líquido'],
            'rows' => $items->map(fn ($d) => [
                $d->disbursement_number, $d->loan->loan_number, $d->loan->client->full_name, (string) $d->gross_amount, (string) $d->deducted_fees,
                (string) $d->net_amount, $d->method->label(), $d->reference, $d->disbursed_at?->format('d/m/Y'), $d->status->label(),
                $d->requester?->name, $d->authorizer?->name,
            ])->all(),
            'summary' => [
                'Desembolsos efectuados' => $items->where('status', 'completed')->count(),
                'Valor líquido entregue' => Money::format(Money::sum($items->filter(fn ($d) => $d->status->value === 'completed'), 'net_amount')),
            ],
            'filters' => ['estado' => \App\Enums\DisbursementStatus::options()],
        ];
    }

    private function payments(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $items = Payment::with('loan', 'client', 'registrar', 'confirmer')
            ->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
            ->when($r->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->when($r->query('metodo'), fn ($q, $m) => $q->where('method', $m))
            ->orderBy('paid_at')->get();
        $confirmed = $items->filter(fn ($p) => $p->status === PaymentStatus::Confirmed);

        return [
            'columns' => ['N.º', 'Recibo', 'Data', 'Cliente', 'Empréstimo', 'Método', 'Referência', 'Tipo', 'Valor', 'Estado', 'Origem', 'Confirmado por'],
            'money' => ['Valor'],
            'rows' => $items->map(fn ($p) => [
                $p->payment_number, $p->receipt_number, $p->paid_at->format('d/m/Y'), $p->client->full_name, $p->loan->loan_number,
                $p->method->label(), $p->reference, $p->typeLabel(), (string) $p->amount, $p->status->label(), $p->sourceLabel(), $p->confirmer?->name,
            ])->all(),
            'summary' => [
                'Pagamentos confirmados' => $confirmed->count(),
                'Total confirmado' => Money::format(Money::sum($confirmed, 'amount')),
                'Pendentes' => $items->where('status', PaymentStatus::Pending)->count(),
                'Estornados' => $items->where('status', PaymentStatus::Reversed)->count(),
            ],
            'filters' => ['estado' => PaymentStatus::options(), 'metodo' => PaymentMethod::options()],
        ];
    }

    private function installments(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $items = LoanInstallment::with('loan.client')
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', InstallmentStatus::Restructured)
            ->when($r->query('estado') === 'overdue', fn ($q) => $q->whereIn('status', InstallmentStatus::openValues())->whereDate('due_date', '<', today()))
            ->when(in_array($r->query('estado'), ['pending', 'partial', 'paid'], true), fn ($q) => $q->where('status', $r->query('estado')))
            ->orderBy('due_date')->get();

        return [
            'columns' => ['Empréstimo', 'Cliente', 'N.º', 'Vencimento', 'Capital', 'Juros', 'Encargos', 'Penalizações', 'Valor previsto', 'Pago', 'Pendente', 'Dias atraso', 'Estado'],
            'money' => ['Capital', 'Juros', 'Encargos', 'Penalizações', 'Valor previsto', 'Pago', 'Pendente'],
            'numeric' => ['N.º', 'Dias atraso'],
            'rows' => $items->map(fn ($i) => [
                $i->loan->loan_number, $i->loan->client->full_name, $i->number, $i->due_date->format('d/m/Y'), (string) $i->principal_due,
                (string) $i->interest_due, (string) $i->fee_due, (string) $i->penalty_due, (string) $i->total_due, (string) $i->total_paid,
                $i->outstandingTotal(), $i->days_overdue, $i->displayStatus()['label'],
            ])->all(),
            'summary' => [
                'Prestações' => $items->count(),
                'Valor previsto' => Money::format(Money::sum($items, 'total_due')),
                'Pago' => Money::format(Money::sum($items, 'total_paid')),
                'Pendente' => Money::format(Money::sum($items->map->outstandingTotal())),
            ],
            'filters' => ['estado' => ['pending' => 'Por pagar', 'partial' => 'Parcial', 'paid' => 'Paga', 'overdue' => 'Em atraso']],
        ];
    }

    private function allocationsByMonth(CarbonImmutable $from, CarbonImmutable $to)
    {
        return PaymentAllocation::query()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payments.status', PaymentStatus::Confirmed->value)
            ->where('payment_allocations.is_reversed', false)
            ->whereBetween('payments.paid_at', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("DATE_FORMAT(payments.paid_at, '%Y-%m') m, SUM(payment_allocations.principal) p, SUM(payment_allocations.interest) i, SUM(payment_allocations.fee) f, SUM(payment_allocations.penalty) pe, SUM(payment_allocations.total) t")
            ->groupBy('m')->orderBy('m')->get()->keyBy('m');
    }

    private function interest(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $rows = $this->allocationsByMonth($from, $to);

        return [
            'columns' => ['Mês', 'Capital recuperado', 'Juros', 'Encargos', 'Penalizações', 'Total recebido'],
            'money' => ['Capital recuperado', 'Juros', 'Encargos', 'Penalizações', 'Total recebido'],
            'rows' => $rows->map(fn ($x) => [CarbonImmutable::parse($x->m.'-01')->translatedFormat('F Y'), $x->p, $x->i, $x->f, $x->pe, $x->t])->values()->all(),
            'summary' => [
                'Juros recebidos' => Money::format(Money::sum($rows, 'i')),
                'Encargos recebidos' => Money::format(Money::sum($rows, 'f')),
                'Penalizações recebidas' => Money::format(Money::sum($rows, 'pe')),
            ],
        ];
    }

    private function outstanding(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $loans = Loan::with('product')->where('status', LoanStatus::Active)->get()->groupBy('product.name');

        return [
            'columns' => ['Produto', 'Empréstimos activos', 'Capital contratado', 'Capital em dívida', 'Total em dívida', 'Capital em atraso', '% em risco'],
            'money' => ['Capital contratado', 'Capital em dívida', 'Total em dívida', 'Capital em atraso'],
            'numeric' => ['Empréstimos activos', '% em risco'],
            'rows' => $loans->map(function ($group, $name) {
                $principal = Money::sum($group, 'principal_outstanding');
                $risk = Money::sum($group->where('days_past_due', '>', 0), 'principal_outstanding');

                return [$name, $group->count(), Money::sum($group, 'principal_amount'), $principal, Money::sum($group, 'total_outstanding'), $risk,
                    Money::isPositive($principal) ? str_replace('.', ',', Money::round(Money::mul(Money::div($risk, $principal), '100'), 1)).'%' : '0%'];
            })->values()->all(),
            'summary' => [
                'Capital em dívida (total)' => Money::format(Money::sum($loans->flatten(), 'principal_outstanding')),
                'Situação à data' => now()->format('d/m/Y H:i'),
            ],
        ];
    }

    private function collections(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $items = CollectionCase::with('loan', 'client', 'assignee')->withCount('contacts', 'promises')
            ->when(! $r->boolean('todos'), fn ($q) => $q->where(fn ($w) => $w->whereIn('status', ['open', 'promised'])
                ->orWhereBetween('opened_at', [$from->startOfDay(), $to->endOfDay()])))
            ->when($r->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('days_past_due')->get();

        return [
            'columns' => ['Empréstimo', 'Cliente', 'Telefone', 'Dias atraso', 'Valor em atraso', 'Escalão', 'Estado', 'Responsável', 'Contactos', 'Promessas', 'Último contacto', 'Aberto em'],
            'money' => ['Valor em atraso'],
            'numeric' => ['Dias atraso', 'Contactos', 'Promessas'],
            'rows' => $items->map(fn ($c) => [
                $c->loan->loan_number, $c->client->full_name, $c->client->phone, $c->days_past_due, (string) $c->overdue_amount, $c->agingBucket(),
                $c->statusLabel(), $c->assignee?->name, $c->contacts_count, $c->promises_count, $c->last_contact_at?->format('d/m/Y'), $c->opened_at->format('d/m/Y'),
            ])->all(),
            'summary' => [
                'Processos abertos' => $items->whereIn('status', ['open', 'promised'])->count(),
                'Valor em atraso' => Money::format(Money::sum($items->whereIn('status', ['open', 'promised']), 'overdue_amount')),
            ],
            'filters' => ['estado' => CollectionCase::STATUSES],
        ];
    }

    private function default(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $loans = Loan::where('status', LoanStatus::Active)->get();
        $total = Money::sum($loans, 'principal_outstanding');
        $buckets = [
            'Em dia' => fn ($l) => $l->days_past_due === 0,
            '1–30 dias' => fn ($l) => $l->days_past_due >= 1 && $l->days_past_due <= 30,
            '31–60 dias' => fn ($l) => $l->days_past_due >= 31 && $l->days_past_due <= 60,
            '61–90 dias' => fn ($l) => $l->days_past_due >= 61 && $l->days_past_due <= 90,
            'Mais de 90 dias' => fn ($l) => $l->days_past_due > 90,
        ];
        $rows = [];
        foreach ($buckets as $label => $fn) {
            $group = $loans->filter($fn);
            $principal = Money::sum($group, 'principal_outstanding');
            $rows[] = [$label, $group->count(), $principal, Money::sum($group, 'total_outstanding'),
                Money::isPositive($total) ? str_replace('.', ',', Money::round(Money::mul(Money::div($principal, $total), '100'), 1)).'%' : '0%'];
        }
        $par = fn ($d) => Money::isPositive($total) ? str_replace('.', ',', Money::round(Money::mul(Money::div(Money::sum($loans->where('days_past_due', '>', $d), 'principal_outstanding'), $total), '100'), 2)).'%' : '0%';

        return [
            'columns' => ['Escalão de atraso', 'Empréstimos', 'Capital em dívida', 'Total em dívida', '% da carteira'],
            'money' => ['Capital em dívida', 'Total em dívida'],
            'numeric' => ['Empréstimos', '% da carteira'],
            'rows' => $rows,
            'summary' => ['PAR 1' => $par(0), 'PAR 30' => $par(30), 'PAR 90' => $par(90), 'Situação à data' => now()->format('d/m/Y')],
        ];
    }

    private function recovery(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $rows = LoanInstallment::whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', InstallmentStatus::Restructured)
            ->selectRaw("DATE_FORMAT(due_date, '%Y-%m') m, COUNT(*) n, SUM(total_due) d, SUM(total_paid) p, SUM(CASE WHEN days_overdue > 0 THEN 1 ELSE 0 END) late")
            ->groupBy('m')->orderBy('m')->get();

        return [
            'columns' => ['Mês de vencimento', 'Prestações', 'Valor devido', 'Valor recuperado', 'Por recuperar', 'Taxa de recuperação', 'Com atraso'],
            'money' => ['Valor devido', 'Valor recuperado', 'Por recuperar'],
            'numeric' => ['Prestações', 'Taxa de recuperação', 'Com atraso'],
            'rows' => $rows->map(fn ($x) => [
                CarbonImmutable::parse($x->m.'-01')->translatedFormat('F Y'), $x->n, $x->d, $x->p, Money::sub($x->d, $x->p),
                Money::isPositive($x->d) ? str_replace('.', ',', Money::round(Money::mul(Money::div($x->p, $x->d), '100'), 1)).'%' : '—', $x->late,
            ])->all(),
            'summary' => [
                'Devido' => Money::format(Money::sum($rows, 'd')),
                'Recuperado' => Money::format(Money::sum($rows, 'p')),
            ],
        ];
    }

    private function performance(CarbonImmutable $from, CarbonImmutable $to, string $format, string $labelFormat): array
    {
        $key = fn ($col) => "DATE_FORMAT({$col}, '{$format}')";
        $disb = Disbursement::where('status', 'completed')->whereBetween('disbursed_at', [$from->toDateString(), $to->toDateString()])
            ->selectRaw($key('disbursed_at').' k, COUNT(*) n, SUM(gross_amount) t')->groupBy('k')->get()->keyBy('k');
        $pay = Payment::where('status', PaymentStatus::Confirmed)->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
            ->selectRaw($key('paid_at').' k, COUNT(*) n, SUM(amount) t')->groupBy('k')->get()->keyBy('k');
        $int = PaymentAllocation::join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payments.status', 'confirmed')->where('is_reversed', false)
            ->whereBetween('payments.paid_at', [$from->toDateString(), $to->toDateString()])
            ->selectRaw($key('payments.paid_at').' k, SUM(payment_allocations.interest) i, SUM(payment_allocations.fee + payment_allocations.penalty) o')->groupBy('k')->get()->keyBy('k');
        $apps = LoanApplication::whereBetween('submitted_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw($key('submitted_at')." k, COUNT(*) n, SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) a")->groupBy('k')->get()->keyBy('k');
        $settled = Loan::whereBetween('settled_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw($key('settled_at').' k, COUNT(*) n')->groupBy('k')->get()->keyBy('k');

        $keys = collect([$disb, $pay, $int, $apps, $settled])->flatMap(fn ($c) => $c->keys())->unique()->sort()->values();

        return [
            'columns' => ['Período', 'Pedidos', 'Aprovados', 'Desembolsos', 'Valor desembolsado', 'Pagamentos', 'Valor recebido', 'Juros recebidos', 'Encargos e penalizações', 'Liquidados'],
            'money' => ['Valor desembolsado', 'Valor recebido', 'Juros recebidos', 'Encargos e penalizações'],
            'numeric' => ['Pedidos', 'Aprovados', 'Desembolsos', 'Pagamentos', 'Liquidados'],
            'rows' => $keys->map(fn ($k) => [
                $labelFormat === 'Y' ? $k : CarbonImmutable::parse($k.'-01')->translatedFormat('F Y'),
                $apps[$k]->n ?? 0, $apps[$k]->a ?? 0, $disb[$k]->n ?? 0, $disb[$k]->t ?? '0', $pay[$k]->n ?? 0, $pay[$k]->t ?? '0',
                $int[$k]->i ?? '0', $int[$k]->o ?? '0', $settled[$k]->n ?? 0,
            ])->all(),
            'summary' => [
                'Total desembolsado' => Money::format(Money::sum($disb, 't')),
                'Total recebido' => Money::format(Money::sum($pay, 't')),
                'Juros recebidos' => Money::format(Money::sum($int, 'i')),
            ],
        ];
    }

    private function monthly(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        return $this->performance($from, $to, '%Y-%m', 'Y-m');
    }

    private function annual(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        return $this->performance($from, $to, '%Y', 'Y');
    }

    private function agents(CarbonImmutable $from, CarbonImmutable $to, Request $r): array
    {
        $range = [$from->startOfDay(), $to->endOfDay()];
        $count = fn ($query, string $col) => $query->whereBetween('created_at', $range)->selectRaw("{$col} u, COUNT(*) c")->groupBy($col)->pluck('c', 'u');

        $reviews = $count(LoanApplicationReview::whereNotIn('action', ['submitted', 'info_provided', 'cancelled']), 'user_id');
        $decisions = LoanApplication::whereBetween('decided_at', $range)->selectRaw('decided_by u, COUNT(*) c')->groupBy('decided_by')->pluck('c', 'u');
        $registered = $count(Payment::where('source', 'staff'), 'registered_by');
        $confirmed = Payment::whereBetween('confirmed_at', $range)->selectRaw('confirmed_by u, COUNT(*) c, SUM(amount) t')->groupBy('confirmed_by')->get()->keyBy('u');
        $disb = Disbursement::whereBetween('requested_at', $range)->selectRaw('requested_by u, COUNT(*) c')->groupBy('requested_by')->pluck('c', 'u');
        $contacts = CollectionContact::whereBetween('contacted_at', $range)->selectRaw('user_id u, COUNT(*) c')->groupBy('user_id')->pluck('c', 'u');
        $portfolio = Loan::where('status', LoanStatus::Active)->selectRaw('loan_officer_id u, COUNT(*) c, SUM(principal_outstanding) p')->groupBy('loan_officer_id')->get()->keyBy('u');

        $users = User::where('is_staff', true)->orderBy('name')->get();

        return [
            'columns' => ['Funcionário', 'Perfil', 'Acções de análise', 'Decisões', 'Pagamentos registados', 'Pagamentos confirmados', 'Valor confirmado', 'Desembolsos registados', 'Contactos de cobrança', 'Carteira (empréstimos)', 'Carteira (capital)'],
            'money' => ['Valor confirmado', 'Carteira (capital)'],
            'numeric' => ['Acções de análise', 'Decisões', 'Pagamentos registados', 'Pagamentos confirmados', 'Desembolsos registados', 'Contactos de cobrança', 'Carteira (empréstimos)'],
            'rows' => $users->map(fn ($u) => [
                $u->name, $u->roleLabel(), $reviews[$u->id] ?? 0, $decisions[$u->id] ?? 0, $registered[$u->id] ?? 0,
                $confirmed[$u->id]->c ?? 0, $confirmed[$u->id]->t ?? '0', $disb[$u->id] ?? 0, $contacts[$u->id] ?? 0,
                $portfolio[$u->id]->c ?? 0, $portfolio[$u->id]->p ?? '0',
            ])->all(),
        ];
    }
}
