<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\Documents\PdfService;
use App\Services\DocumentStorageService;
use App\Services\Payments\PaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PdfService $pdf,
        private readonly DocumentStorageService $storage,
    ) {}

    public function index(Request $request): View
    {
        $payments = Payment::with('loan', 'client', 'registrar')
            ->search($request->query('q'))
            ->when($request->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('metodo'), fn ($q, $m) => $q->where('method', $m))
            ->when($request->query('origem'), fn ($q, $o) => $q->where('source', $o))
            ->when($request->query('de'), fn ($q, $d) => $q->whereDate('paid_at', '>=', $d))
            ->when($request->query('ate'), fn ($q, $d) => $q->whereDate('paid_at', '<=', $d))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'pendingCount' => Payment::where('status', 'pending')->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $loan = null;
        if ($request->query('emprestimo')) {
            $loan = Loan::with('client')->where('status', LoanStatus::Active)->find($request->query('emprestimo'));
        }
        $results = collect();
        if (! $loan && $request->filled('q')) {
            $results = Loan::with('client')->where('status', LoanStatus::Active)->search($request->query('q'))->limit(15)->get();
        }

        return view('admin.payments.create', [
            'loan' => $loan,
            'results' => $results,
            'next' => $loan?->nextInstallment(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'loan_id' => ['required', 'integer', 'exists:loans,id'],
            'amount' => ['required', 'string'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', ...explode('|', DocumentStorageService::validationRule())],
            'confirm_now' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);
        $amount = Money::parseInput($data['amount']);
        if ($amount === null) {
            return back()->withInput()->withErrors(['amount' => 'Valor inválido.']);
        }

        $loan = Loan::findOrFail($data['loan_id']);
        $payment = $this->payments->register($loan, $request->user(), [
            'amount' => $amount, 'method' => $data['method'], 'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'], 'payer_phone' => $data['payer_phone'] ?? null, 'notes' => $data['notes'] ?? null,
        ], 'staff', $request->file('proof'), $data['idempotency_key'] ?? null);

        if ($request->boolean('confirm_now') && $request->user()->can('payments.confirm')) {
            $this->payments->confirm($payment, $request->user());

            return redirect()->route('admin.payments.show', $payment)->with('success', 'Pagamento registado e confirmado. Recibo emitido.');
        }

        return redirect()->route('admin.payments.show', $payment)->with('success', 'Pagamento registado. Aguarda confirmação.');
    }

    public function show(Payment $payment): View
    {
        return view('admin.payments.show', [
            'payment' => $payment->load('loan.client', 'client', 'allocations.installment', 'registrar', 'confirmer', 'rejecter', 'reversal.reverser'),
            'isLatest' => ! Payment::where('loan_id', $payment->loan_id)->where('status', 'confirmed')->where('id', '!=', $payment->id)
                ->where('confirmed_at', '>', $payment->confirmed_at ?? now())->exists(),
        ]);
    }

    public function confirm(Request $request, Payment $payment): RedirectResponse
    {
        $this->payments->confirm($payment, $request->user());

        return back()->with('success', 'Pagamento confirmado e imputado às prestações. Recibo emitido.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->payments->reject($payment, $request->user(), $data['reason']);

        return back()->with('success', 'Pagamento rejeitado.');
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $this->payments->reverse($payment, $request->user(), $data['reason']);

        return back()->with('success', 'Pagamento estornado. Foram lançados os movimentos compensatórios.');
    }

    public function receipt(Payment $payment): Response
    {
        abort_unless($payment->receipt_number, 404);

        return $this->pdf->receipt($payment);
    }

    public function proof(Payment $payment): StreamedResponse
    {
        abort_unless($payment->proof_path, 404);

        return $this->storage->download($payment->proof_path, 'comprovativo-'.$payment->payment_number.'.'.pathinfo($payment->proof_path, PATHINFO_EXTENSION));
    }
}
