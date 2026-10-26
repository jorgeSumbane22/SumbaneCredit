<?php

namespace App\Http\Controllers\Client;

use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentStatus;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends ClientController
{
    public function index(): View
    {
        $client = $this->client();
        $activeLoans = $client->loans()->where('status', LoanStatus::Active)->get();

        $nextInstallment = LoanInstallment::with('loan')
            ->whereIn('loan_id', $activeLoans->pluck('id'))
            ->whereIn('status', InstallmentStatus::openValues())
            ->whereHas('loan', fn ($q) => $q->whereColumn('loans.schedule_version', 'loan_installments.schedule_version'))
            ->orderBy('due_date')->first();

        $months = collect(range(11, 0))->map(fn ($i) => CarbonImmutable::today()->startOfMonth()->subMonths($i));
        $paid = $client->payments()->where('status', PaymentStatus::Confirmed)
            ->where('paid_at', '>=', $months->first()->toDateString())->get()
            ->groupBy(fn ($p) => $p->paid_at->format('Y-m'));

        $activity = collect()
            ->merge($client->applications()->with('reviews')->limit(5)->get()->flatMap(fn (LoanApplication $a) => $a->reviews
                ->filter->isClientVisible()
                ->map(fn ($r) => ['at' => $r->created_at, 'icon' => 'file-earmark-text', 'text' => $r->actionLabel().' — '.$a->application_number])))
            ->merge($client->payments()->limit(10)->get()->map(fn ($p) => [
                'at' => $p->updated_at, 'icon' => 'cash-stack',
                'text' => 'Pagamento '.Money::format($p->amount).' — '.$p->status->label(),
            ]))
            ->sortByDesc('at')->take(8)->values();

        return view('client.dashboard', [
            'client' => $client,
            'activeLoansCount' => $activeLoans->count(),
            'pendingApplications' => $client->applications()->open()->count(),
            'outstandingPrincipal' => Money::sum($activeLoans, 'principal_outstanding'),
            'outstandingTotal' => Money::sum($activeLoans, 'total_outstanding'),
            'totalPaid' => (string) $client->payments()->where('status', PaymentStatus::Confirmed)->sum('amount'),
            'nextInstallment' => $nextInstallment,
            'notifications' => auth()->user()->notifications()->latest()->limit(5)->get(),
            'activity' => $activity,
            'chart' => [
                'labels' => $months->map(fn ($m) => ucfirst($m->translatedFormat('M/y')))->all(),
                'values' => $months->map(fn ($m) => (float) Money::sum($paid->get($m->format('Y-m'), collect()), 'amount'))->all(),
            ],
            'profileIncomplete' => ! $client->address || ! $client->employment || $client->documents()->doesntExist(),
        ]);
    }
}
