<?php

namespace App\Http\Controllers\Client;

use App\Models\Loan;
use App\Services\Documents\PdfService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LoanController extends ClientController
{
    public function __construct(private readonly PdfService $pdf) {}

    public function index(): View
    {
        return view('client.loans.index', [
            'loans' => $this->client()->loans()->with('product')->paginate(10),
        ]);
    }

    public function show(Loan $loan): View
    {
        $this->authorize('view', $loan);
        $loan->load('product', 'disbursement', 'guarantors', 'collaterals');

        return view('client.loans.show', [
            'loan' => $loan,
            'installments' => $loan->currentInstallments()->get(),
            'payments' => $loan->payments()->limit(10)->get(),
            'next' => $loan->nextInstallment(),
            'schedule' => $loan->currentInstallments()->exists() ? null : $this->pdf->scheduleRows($loan),
        ]);
    }

    public function contract(Loan $loan): Response
    {
        $this->authorize('view', $loan);

        return $this->pdf->contract($loan);
    }

    public function schedule(Loan $loan): Response
    {
        $this->authorize('view', $loan);

        return $this->pdf->schedule($loan);
    }

    public function statement(Loan $loan): Response
    {
        $this->authorize('view', $loan);

        return $this->pdf->statement($loan);
    }
}
