<?php

namespace App\Services\Documents;

use App\Enums\LoanStatus;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\AuditService;
use App\Services\Loans\ScheduleService;
use App\Services\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documentos PDF gerados a partir de dados reais. Não incluem assinaturas digitais nem
 * qualquer garantia de validade jurídica: os campos de assinatura são preenchidos à mão.
 */
class PdfService
{
    public function __construct(
        private readonly ScheduleService $schedule,
        private readonly SettingsService $settings,
        private readonly AuditService $audit,
    ) {}

    private function institution(): array
    {
        return [
            'name' => $this->settings->get('institution.name'),
            'legal_name' => $this->settings->get('institution.legal_name'),
            'nuit' => $this->settings->get('institution.nuit'),
            'address' => $this->settings->get('institution.address'),
            'phone' => $this->settings->get('institution.phone'),
            'email' => $this->settings->get('institution.email'),
            'registration_info' => $this->settings->get('institution.registration_info'),
        ];
    }

    private function render(string $view, array $data, string $filename, string $orientation = 'portrait'): Response
    {
        $pdf = Pdf::loadView($view, $data + ['institution' => $this->institution(), 'generatedAt' => now()])
            ->setPaper('a4', $orientation)
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);

        return $pdf->stream($filename);
    }

    /** Linhas do plano: prestações reais (após desembolso) ou plano previsional. */
    public function scheduleRows(Loan $loan): array
    {
        if ($loan->currentInstallments()->exists()) {
            return [
                'provisional' => false,
                'rows' => $loan->currentInstallments()->get()->map(fn ($i) => [
                    'number' => $i->number, 'due_date' => $i->due_date->toDateString(), 'opening_balance' => (string) $i->opening_balance,
                    'principal' => (string) $i->principal_due, 'interest' => (string) $i->interest_due, 'fee' => (string) $i->fee_due,
                    'penalty' => (string) $i->penalty_due, 'total' => (string) $i->total_due, 'paid' => (string) $i->total_paid,
                    'status' => $i->displayStatus()['label'],
                ])->all(),
            ];
        }
        $preview = $this->schedule->preview($loan, CarbonImmutable::today());

        return [
            'provisional' => true,
            'rows' => array_map(fn ($r) => $r + ['penalty' => '0.00', 'paid' => '0.00', 'status' => 'Previsional'], $preview['rows']),
        ];
    }

    public function contract(Loan $loan): Response
    {
        $loan->load('client.address', 'client.employment', 'product', 'guarantors', 'collaterals', 'approver', 'application');
        $this->audit->log('document.contract_generated', $loan);

        return $this->render('pdf.contract', ['loan' => $loan, 'schedule' => $this->scheduleRows($loan)], "contrato-{$loan->contract_number}.pdf");
    }

    public function schedule(Loan $loan): Response
    {
        $loan->load('client', 'product');

        return $this->render('pdf.schedule', ['loan' => $loan, 'schedule' => $this->scheduleRows($loan)], "plano-{$loan->loan_number}.pdf", 'landscape');
    }

    public function statement(Loan $loan): Response
    {
        $loan->load('client', 'product');
        $payments = $loan->payments()->whereIn('status', ['confirmed', 'reversed'])->with('reversal')->orderBy('paid_at')->get();

        return $this->render('pdf.statement', [
            'loan' => $loan,
            'transactions' => $loan->transactions()->where('component', '!=', 'cash')->get(),
            'payments' => $payments,
            'schedule' => $this->scheduleRows($loan),
        ], "extracto-{$loan->loan_number}.pdf");
    }

    public function receipt(Payment $payment): Response
    {
        $payment->load('loan.product', 'client', 'allocations.installment', 'confirmer', 'reversal');

        return $this->render('pdf.receipt', ['payment' => $payment], "recibo-{$payment->receipt_number}.pdf");
    }

    public function disbursement(Disbursement $disbursement): Response
    {
        $disbursement->load('loan.client', 'loan.product', 'requester', 'authorizer', 'executor');

        return $this->render('pdf.disbursement', ['disbursement' => $disbursement], "desembolso-{$disbursement->disbursement_number}.pdf");
    }

    public function report(array $report): Response
    {
        return $this->render('pdf.report', ['report' => $report], 'relatorio-'.$report['key'].'-'.now()->format('Ymd-His').'.pdf',
            count($report['columns']) > 7 ? 'landscape' : 'portrait');
    }

    public function isDraftContract(Loan $loan): bool
    {
        return $loan->status === LoanStatus::AwaitingContract;
    }
}
