<?php

namespace App\Http\Controllers\Client;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
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

class PaymentController extends ClientController
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PdfService $pdf,
    ) {}

    public function index(Request $request): View
    {
        $payments = $this->client()->payments()->with('loan')
            ->when($request->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->paginate(15)->withQueryString();

        return view('client.payments.index', ['payments' => $payments, 'status' => $request->query('estado')]);
    }

    public function create(Request $request): View
    {
        $loans = $this->client()->loans()->where('status', LoanStatus::Active)->get();

        return view('client.payments.create', [
            'loans' => $loans,
            'selected' => $request->query('emprestimo'),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    /** Submissão de comprovativo: fica pendente até confirmação por um funcionário. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'loan_id' => ['required', 'integer'],
            'amount' => ['required', 'string'],
            'method' => ['required', Rule::in([PaymentMethod::Mpesa->value, PaymentMethod::Emola->value, PaymentMethod::BankTransfer->value, PaymentMethod::BankDeposit->value])],
            'reference' => ['required', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'proof' => ['required', ...explode('|', DocumentStorageService::validationRule())],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ], [], ['amount' => 'valor', 'reference' => 'referência', 'paid_at' => 'data do pagamento', 'proof' => 'comprovativo']);

        // O empréstimo tem de pertencer ao cliente autenticado.
        $loan = $this->client()->loans()->whereKey($data['loan_id'])->firstOrFail();
        $this->authorize('pay', $loan);

        $amount = Money::parseInput($data['amount']);
        if ($amount === null) {
            return back()->withInput()->withErrors(['amount' => 'Valor inválido.']);
        }

        $payment = $this->payments->register($loan, $request->user(), [
            'amount' => $amount,
            'method' => $data['method'],
            'reference' => $data['reference'],
            'paid_at' => $data['paid_at'],
            'payer_phone' => $data['payer_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], 'client_portal', $request->file('proof'), $data['idempotency_key'] ?? null);

        return redirect()->route('client.payments.show', $payment)->with('success', 'Comprovativo submetido. O pagamento será confirmado após verificação.');
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);
        $payment->load('loan', 'allocations.installment', 'reversal');

        return view('client.payments.show', ['payment' => $payment]);
    }

    public function receipt(Payment $payment): Response
    {
        $this->authorize('receipt', $payment);

        return $this->pdf->receipt($payment);
    }
}
