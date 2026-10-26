<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Services\Documents\PdfService;
use App\Services\DocumentStorageService;
use App\Services\Loans\DisbursementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DisbursementController extends Controller
{
    public function __construct(
        private readonly DisbursementService $disbursements,
        private readonly PdfService $pdf,
        private readonly DocumentStorageService $storage,
    ) {}

    public function index(Request $request): View
    {
        $items = Disbursement::with('loan.client', 'requester', 'authorizer')
            ->when($request->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('metodo'), fn ($q, $m) => $q->where('method', $m))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('disbursement_number', 'like', "%{$t}%")
                ->orWhere('reference', 'like', "%{$t}%")->orWhereHas('loan', fn ($l) => $l->search($t))))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.disbursements.index', [
            'items' => $items,
            'ready' => Loan::with('client')->where('status', 'contracted')->whereDoesntHave('disbursements', fn ($q) => $q->where('status', '!=', 'cancelled'))->get(),
        ]);
    }

    public function store(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'destination_account' => ['nullable', 'required_unless:method,cash', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ], ['destination_account.required_unless' => 'Indique o número/conta de destino.']);
        $d = $this->disbursements->request($loan, $request->user(), $data, $data['idempotency_key'] ?? null);

        return redirect()->route('admin.disbursements.show', $d)->with('success', 'Desembolso registado. Aguarda autorização.');
    }

    public function show(Disbursement $disbursement): View
    {
        return view('admin.disbursements.show', ['d' => $disbursement->load('loan.client', 'requester', 'authorizer', 'executor')]);
    }

    public function authorizeDisbursement(Request $request, Disbursement $disbursement): RedirectResponse
    {
        $this->disbursements->authorize($disbursement, $request->user(), $request->input('notes'));

        return back()->with('success', 'Desembolso autorizado. Aguarda a execução da transferência.');
    }

    public function complete(Request $request, Disbursement $disbursement): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:100'],
            'disbursed_at' => ['required', 'date', 'before_or_equal:today'],
            'proof' => ['nullable', ...explode('|', DocumentStorageService::validationRule())],
        ]);
        $this->disbursements->complete($disbursement, $request->user(), $data, $request->file('proof'));

        return back()->with('success', 'Desembolso confirmado. O empréstimo está activo e o plano de prestações foi gerado.');
    }

    public function cancel(Request $request, Disbursement $disbursement): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->disbursements->cancel($disbursement, $request->user(), $data['reason']);

        return back()->with('success', 'Desembolso cancelado.');
    }

    public function pdf(Disbursement $disbursement): Response
    {
        return $this->pdf->disbursement($disbursement);
    }

    public function proof(Disbursement $disbursement): StreamedResponse
    {
        abort_unless($disbursement->proof_path, 404);

        return $this->storage->download($disbursement->proof_path, 'comprovativo-'.$disbursement->disbursement_number.'.'.pathinfo($disbursement->proof_path, PATHINFO_EXTENSION));
    }
}
