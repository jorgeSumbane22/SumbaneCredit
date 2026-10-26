<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\Disbursement;
use App\Models\FinancialTransaction;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Indicadores do painel calculados a partir da base de dados (sem valores fixos). */
class DashboardService
{
    public function metrics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $fromD = $from->toDateString();
        $toD = $to->toDateString();

        $activeLoans = Loan::where('status', LoanStatus::Active);
        $outstandingPrincipal = (string) (clone $activeLoans)->sum('principal_outstanding');
        $atRisk = (string) Loan::where('status', LoanStatus::Active)->where('days_past_due', '>', 0)->sum('principal_outstanding');
        $par30 = (string) Loan::where('status', LoanStatus::Active)->where('days_past_due', '>', 30)->sum('principal_outstanding');

        $confirmedInPeriod = Payment::where('status', PaymentStatus::Confirmed)->whereBetween('paid_at', [$fromD, $toD]);
        $allocInPeriod = PaymentAllocation::where('is_reversed', false)
            ->whereHas('payment', fn ($q) => $q->where('status', PaymentStatus::Confirmed)->whereBetween('paid_at', [$fromD, $toD]));

        // Recuperação: valores pagos das prestações vencidas no período / valor devido dessas prestações
        $dueInPeriod = LoanInstallment::whereBetween('due_date', [$fromD, $toD])->where('status', '!=', InstallmentStatus::Restructured);
        $due = (string) (clone $dueInPeriod)->sum('total_due');
        $paidOfDue = (string) (clone $dueInPeriod)->sum('total_paid');

        return [
            'clients_total' => Client::count(),
            'clients_active' => Client::where('status', 'active')->whereHas('loans', fn ($q) => $q->where('status', LoanStatus::Active))->count(),
            'clients_new' => Client::whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->count(),
            'applications_pending' => LoanApplication::open()->count(),
            'applications_approved' => LoanApplication::where('status', ApplicationStatus::Approved)->whereBetween('decided_at', [$from->startOfDay(), $to->endOfDay()])->count(),
            'applications_rejected' => LoanApplication::where('status', ApplicationStatus::Rejected)->whereBetween('decided_at', [$from->startOfDay(), $to->endOfDay()])->count(),
            'loans_active' => (clone $activeLoans)->count(),
            'loans_settled' => Loan::where('status', LoanStatus::Settled)->count(),
            'loans_awaiting' => Loan::whereIn('status', [LoanStatus::AwaitingContract, LoanStatus::Contracted])->count(),
            'disbursed' => (string) Disbursement::where('status', 'completed')->whereBetween('disbursed_at', [$fromD, $toD])->sum('gross_amount'),
            'outstanding_principal' => $outstandingPrincipal,
            'outstanding_total' => (string) (clone $activeLoans)->sum('total_outstanding'),
            'interest_received' => (string) (clone $allocInPeriod)->sum('interest'),
            'payments_received' => (string) (clone $confirmedInPeriod)->sum('amount'),
            'payments_pending' => Payment::where('status', PaymentStatus::Pending)->count(),
            'installments_overdue' => LoanInstallment::whereIn('status', InstallmentStatus::openValues())->whereDate('due_date', '<', today())
                ->whereHas('loan', fn ($q) => $q->where('status', LoanStatus::Active)->whereColumn('loans.schedule_version', 'loan_installments.schedule_version'))->count(),
            'portfolio_at_risk' => $atRisk,
            'par_ratio' => Money::isPositive($outstandingPrincipal) ? Money::round(Money::mul(Money::div($atRisk, $outstandingPrincipal), '100'), 1) : '0.0',
            'par30_ratio' => Money::isPositive($outstandingPrincipal) ? Money::round(Money::mul(Money::div($par30, $outstandingPrincipal), '100'), 1) : '0.0',
            'recovery_rate' => Money::isPositive($due) ? Money::round(Money::mul(Money::div($paidOfDue, $due), '100'), 1) : null,
        ];
    }

    public function charts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $months = [];
        $cursor = $from->startOfMonth();
        while ($cursor->lte($to) && count($months) < 36) {
            $months[$cursor->format('Y-m')] = ucfirst($cursor->translatedFormat('M/y'));
            $cursor = $cursor->addMonth();
        }
        $keys = array_keys($months);
        $fill = fn ($rows) => array_map(fn ($k) => (float) ($rows[$k] ?? 0), $keys);

        $disbursed = Disbursement::where('status', 'completed')->whereBetween('disbursed_at', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("DATE_FORMAT(disbursed_at, '%Y-%m') m, SUM(gross_amount) t")->groupBy('m')->pluck('t', 'm')->all();
        $payments = Payment::where('status', PaymentStatus::Confirmed)->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') m, SUM(amount) t")->groupBy('m')->pluck('t', 'm')->all();

        // Carteira (capital em dívida) no fim de cada mês, a partir do razão
        $principalMoves = FinancialTransaction::where('component', 'principal')->where('posted_at', '<=', $to->endOfDay())
            ->selectRaw("DATE_FORMAT(posted_at, '%Y-%m') m, SUM(CASE WHEN direction='debit' THEN amount ELSE -amount END) t")
            ->groupBy('m')->orderBy('m')->pluck('t', 'm')->all();
        $portfolio = [];
        $running = '0';
        $before = collect($principalMoves)->filter(fn ($v, $m) => $m < $keys[0])->sum();
        $running = Money::add($running, (string) $before);
        foreach ($keys as $k) {
            $running = Money::add($running, (string) ($principalMoves[$k] ?? 0));
            $portfolio[] = (float) $running;
        }

        $late = LoanInstallment::whereBetween('due_date', [$from->toDateString(), $to->toDateString()])->where('days_overdue', '>', 0)
            ->selectRaw("DATE_FORMAT(due_date, '%Y-%m') m, COUNT(*) c")->groupBy('m')->pluck('c', 'm')->all();

        $byStatus = Loan::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->all();
        $products = Loan::join('loan_products', 'loan_products.id', '=', 'loans.loan_product_id')
            ->selectRaw('loan_products.name n, COUNT(*) c')->groupBy('loan_products.name')->orderByDesc('c')->limit(6)->pluck('c', 'n')->all();

        return [
            'labels' => array_values($months),
            'disbursed' => $fill($disbursed),
            'payments' => $fill($payments),
            'portfolio' => $portfolio,
            'late' => $fill($late),
            'status_labels' => array_map(fn ($s) => LoanStatus::tryFrom($s)?->label() ?? $s, array_keys($byStatus)),
            'status_values' => array_values($byStatus),
            'product_labels' => array_keys($products),
            'product_values' => array_values($products),
        ];
    }

    /** Listas de trabalho para o utilizador (pendentes). */
    public function worklists(): array
    {
        return [
            'applications' => LoanApplication::with('client', 'product')->open()->oldest('submitted_at')->limit(6)->get(),
            'payments' => Payment::with('client', 'loan')->where('status', PaymentStatus::Pending)->oldest()->limit(6)->get(),
            'disbursements' => Disbursement::with('loan.client')->whereIn('status', ['pending', 'authorized'])->oldest()->limit(6)->get(),
            'overdue' => Loan::with('client')->overdue()->orderByDesc('days_past_due')->limit(6)->get(),
        ];
    }
}
