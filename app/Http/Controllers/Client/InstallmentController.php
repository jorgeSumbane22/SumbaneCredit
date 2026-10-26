<?php

namespace App\Http\Controllers\Client;

use App\Enums\InstallmentStatus;
use App\Models\LoanInstallment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstallmentController extends ClientController
{
    public function index(Request $request): View
    {
        $loanIds = $this->client()->loans()->pluck('id');
        $filter = $request->query('filtro', 'em-aberto');

        $installments = LoanInstallment::with('loan')
            ->whereIn('loan_id', $loanIds)
            ->whereHas('loan', fn ($q) => $q->whereColumn('loans.schedule_version', 'loan_installments.schedule_version'))
            ->when($filter === 'em-aberto', fn ($q) => $q->whereIn('status', InstallmentStatus::openValues()))
            ->when($filter === 'em-atraso', fn ($q) => $q->whereIn('status', InstallmentStatus::openValues())->whereDate('due_date', '<', today()))
            ->when($filter === 'pagas', fn ($q) => $q->where('status', InstallmentStatus::Paid))
            ->when($request->query('emprestimo'), fn ($q, $id) => $q->where('loan_id', $id))
            ->orderBy('due_date')
            ->paginate(20)->withQueryString();

        return view('client.installments', [
            'installments' => $installments,
            'filter' => $filter,
            'loans' => $this->client()->loans()->whereIn('status', ['active', 'settled'])->get(),
        ]);
    }
}
