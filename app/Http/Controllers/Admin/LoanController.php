<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AmortizationMethod;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Enums\RepaymentFrequency;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Penalty;
use App\Services\AuditService;
use App\Services\Documents\PdfService;
use App\Services\DocumentStorageService;
use App\Services\Loans\LoanService;
use App\Services\Loans\PenaltyService;
use App\Services\Loans\RestructuringService;
use App\Services\Payments\ReconciliationService;
use App\Services\Payments\SettlementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanController extends Controller
{
    public function __construct(
        private readonly LoanService $loans,
        private readonly PdfService $pdf,
        private readonly SettlementService $settlement,
        private readonly RestructuringService $restructuring,
        private readonly PenaltyService $penalties,
        private readonly ReconciliationService $reconciliation,
        private readonly DocumentStorageService $storage,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $loans = Loan::with('client', 'product', 'officer')
            ->search($request->query('q'))
            ->when($request->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('produto'), fn ($q, $p) => $q->where('loan_product_id', $p))
            ->when($request->query('atraso') === 'sim', fn ($q) => $q->overdue())
            ->when($request->query('atraso') === '30', fn ($q) => $q->overdue()->where('days_past_due', '>', 30))
            ->when($request->query('atraso') === '90', fn ($q) => $q->overdue()->where('days_past_due', '>', 90))
            ->latest()
            ->paginate(20)->withQueryString();

        return view('admin.loans.index', [
            'loans' => $loans,
            'products' => LoanProduct::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(Loan $loan): View
    {
        $loan->load('client.address', 'product', 'productVersion', 'application', 'approver', 'officer', 'disbursements.requester',
            'disbursements.authorizer', 'payments.confirmer', 'statusHistory.user', 'restructurings.approver', 'guarantors', 'collaterals', 'collections');

        return view('admin.loans.show', [
            'loan' => $loan,
            'installments' => $loan->currentInstallments()->with('penalty')->get(),
            'oldInstallments' => $loan->allInstallments()->where('schedule_version', '!=', $loan->schedule_version)->get(),
            'transactions' => $loan->transactions()->with('creator')->get(),
            'penaltiesList' => $loan->penalties()->with('installment', 'waiver')->latest()->get(),
            'reconciliation' => $loan->isActive() || $loan->status === LoanStatus::Settled ? $this->reconciliation->checkLoan($loan) : null,
            'preview' => $loan->currentInstallments()->exists() ? null : $this->pdf->scheduleRows($loan),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function formalize(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'contract_signed_at' => ['required', 'date', 'before_or_equal:today'],
            'signed_contract' => ['nullable', ...explode('|', DocumentStorageService::validationRule())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->loans->formalize($loan, $request->user(), $data['contract_signed_at'], $request->file('signed_contract'), $data['notes'] ?? null);

        return back()->with('success', 'Contrato formalizado. O empréstimo pode agora ser desembolsado.');
    }

    public function cancel(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->loans->cancel($loan, $request->user(), $data['reason']);

        return back()->with('success', 'Empréstimo cancelado.');
    }

    public function restructure(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'term' => ['required', 'integer', 'min:1', 'max:600'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:1000'],
            'amortization_method' => ['required', Rule::enum(AmortizationMethod::class)],
            'repayment_frequency' => ['required', Rule::enum(RepaymentFrequency::class)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'capitalize_overdue' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $this->restructuring->restructure($loan, $request->user(), $data);

        return back()->with('success', 'Empréstimo reestruturado. Foi gerado um novo plano de prestações.');
    }

    public function settlement(Request $request, Loan $loan): View
    {
        $asOf = CarbonImmutable::parse($request->query('data', today()->toDateString()));
        if ($asOf->gt(CarbonImmutable::today())) {
            $asOf = CarbonImmutable::today();
        }

        return view('admin.loans.settlement', [
            'loan' => $loan->load('client'),
            'quote' => $this->settlement->quote($loan, $asOf),
            'asOf' => $asOf,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function settle(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);
        $payment = $this->settlement->register($loan, $request->user(), $data, 'staff', null, $data['idempotency_key'] ?? null);

        return redirect()->route('admin.payments.show', $payment)->with('success', 'Pagamento de liquidação registado. Confirme-o para concluir a liquidação.');
    }

    public function waivePenalty(Request $request, Penalty $penalty): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->penalties->waive($penalty, $request->user(), $data['reason']);

        return back()->with('success', 'Penalização dispensada.');
    }

    public function reconcile(Request $request, Loan $loan): RedirectResponse
    {
        $record = $this->reconciliation->reconcileLoan($loan, $request->user(), $request->input('notes'));

        return back()->with($record->status === 'matched' ? 'success' : 'error', $record->status === 'matched'
            ? 'Reconciliação concluída: saldos, prestações e movimentos coincidem.'
            : 'Reconciliação com diferenças. Consulte o detalhe e a auditoria.');
    }

    public function contract(Loan $loan): Response
    {
        return $this->pdf->contract($loan);
    }

    public function signedContract(Loan $loan): StreamedResponse
    {
        abort_unless($loan->signed_contract_path, 404);
        $this->audit->log('document.downloaded', $loan, null, null, 'Download do contrato assinado');

        return $this->storage->download($loan->signed_contract_path, 'contrato-assinado-'.$loan->contract_number.'.'.pathinfo($loan->signed_contract_path, PATHINFO_EXTENSION));
    }

    public function schedule(Loan $loan): Response
    {
        return $this->pdf->schedule($loan);
    }

    public function statement(Loan $loan): Response
    {
        return $this->pdf->statement($loan);
    }
}
