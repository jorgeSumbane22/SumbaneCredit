<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoanApplicationRequest;
use App\Models\Client;
use App\Models\Collateral;
use App\Models\Guarantor;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Credit\CreditAssessmentService;
use App\Services\Credit\LoanApplicationService;
use App\Support\Money;
use App\Support\ProductCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanApplicationController extends Controller
{
    public function __construct(
        private readonly LoanApplicationService $applications,
        private readonly CreditAssessmentService $assessment,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $applications = LoanApplication::with('client', 'product', 'assignee', 'latestAssessment')
            ->search($request->query('q'))
            ->when($request->query('estado') === 'abertos', fn ($q) => $q->open())
            ->when($request->query('estado') && $request->query('estado') !== 'abertos', fn ($q) => $q->where('status', $request->query('estado')))
            ->when($request->query('produto'), fn ($q, $p) => $q->where('loan_product_id', $p))
            ->when($request->query('responsavel') === 'eu', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($request->query('de'), fn ($q, $d) => $q->whereDate('submitted_at', '>=', $d))
            ->when($request->query('ate'), fn ($q, $d) => $q->whereDate('submitted_at', '<=', $d))
            ->orderByRaw("FIELD(status, 'submitted','pending_documents','under_review','forwarded') = 0, submitted_at desc")
            ->paginate(20)->withQueryString();

        return view('admin.applications.index', [
            'applications' => $applications,
            'products' => LoanProduct::orderBy('name')->pluck('name', 'id'),
            'counts' => LoanApplication::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function create(Client $client): View
    {
        $client->load('employment', 'address');
        $products = LoanProduct::active()->with('currentVersion')->orderBy('name')->get();

        return view('admin.applications.create', [
            'client' => $client,
            'products' => $products,
            'catalog' => ProductCatalog::simulatorData($products),
            'selected' => null,
            'documents' => $client->documents()->where('status', '!=', 'rejected')->get(),
            'action' => route('admin.clients.applications.store', $client),
            'simulateUrl' => route('admin.products.simulate'),
        ]);
    }

    /** Pedido registado ao balcão em nome do cliente (com as declarações do cliente). */
    public function store(LoanApplicationRequest $request, Client $client): RedirectResponse
    {
        $application = $this->applications->submit($client, $request->payload(), $request->user());

        return redirect()->route('admin.applications.show', $application)->with('success', 'Pedido registado.');
    }

    public function show(LoanApplication $application): View
    {
        $application->load('client.address', 'client.employment', 'client.loans', 'product', 'productVersion', 'reviews.user',
            'documents.verifier', 'guarantors', 'collaterals', 'assessments.performer', 'loan', 'assignee', 'decider');
        $this->audit->log('application.viewed', $application, null, null, 'Consulta do pedido');

        return view('admin.applications.show', [
            'application' => $application,
            'analysts' => User::permission('applications.review')->where('status', 'active')->where('is_staff', true)->orderBy('name')->get(),
            'latest' => $application->assessments->first(),
            'disposable' => Money::sub(Money::sub($application->totalIncome(), $application->monthly_expenses), $application->existing_obligations),
        ]);
    }

    public function startReview(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->authorize('review', $application);
        $this->applications->startReview($application, $request->user());

        return back()->with('success', 'Análise iniciada.');
    }

    public function note(Request $request, LoanApplication $application): RedirectResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:2000']]);
        $this->applications->addNote($application, $request->user(), $data['notes']);

        return back()->with('success', 'Observação registada.');
    }

    public function assess(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->authorize('review', $application);
        $result = $this->assessment->assess($application, $request->user());

        return back()->with('success', 'Avaliação concluída: '.$result->resultLabel().'.');
    }

    public function requestInformation(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->authorize('review', $application);
        $data = $request->validate(['message' => ['required', 'string', 'min:10', 'max:2000']]);
        $this->applications->requestInformation($application, $request->user(), $data['message']);

        return back()->with('success', 'Pedido de informação enviado ao cliente.');
    }

    public function forward(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->authorize('review', $application);
        $this->applications->forward($application, $request->user(), $request->input('notes'));

        return back()->with('success', 'Pedido encaminhado para decisão.');
    }

    public function assign(Request $request, LoanApplication $application): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $assignee = User::where('is_staff', true)->findOrFail($data['user_id']);
        abort_unless($assignee->can('applications.review'), 422, 'O utilizador não tem permissão de análise.');
        $this->applications->assign($application, $assignee, $request->user());

        return back()->with('success', 'Pedido atribuído a '.$assignee->name.'.');
    }

    public function approve(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->authorize('decide', $application);
        $data = $request->validate([
            'approved_amount' => ['required', 'numeric', 'min:1'],
            'approved_term' => ['required', 'integer', 'min:1', 'max:600'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
            'confirm_unfavorable' => ['nullable', 'boolean'],
        ]);
        $latest = $application->assessments()->first();
        if ($latest && $latest->result === 'unfavorable' && ! $request->boolean('confirm_unfavorable')) {
            return back()->withInput()->with('error', 'A avaliação mais recente é desfavorável. Confirme expressamente a aprovação e justifique a decisão.');
        }
        if ($latest && $latest->result === 'unfavorable' && empty($data['decision_notes'])) {
            return back()->withInput()->with('error', 'Justifique a aprovação contra a recomendação desfavorável.');
        }

        $loan = $this->applications->approve($application, $request->user(), (string) $data['approved_amount'], (int) $data['approved_term'], $data['decision_notes'] ?? null);

        return redirect()->route('admin.loans.show', $loan)->with('success', 'Pedido aprovado. Empréstimo '.$loan->loan_number.' criado — aguarda formalização do contrato.');
    }

    public function reject(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->authorize('decide', $application);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $this->applications->reject($application, $request->user(), $data['reason']);

        return back()->with('success', 'Pedido rejeitado. O cliente foi notificado.');
    }

    public function cancel(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->authorize('cancel', $application);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->applications->cancel($application, $request->user(), $data['reason']);

        return back()->with('success', 'Pedido cancelado.');
    }

    public function collateralStatus(Request $request, Collateral $collateral): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Collateral::STATUSES))]]);
        $old = $collateral->status;
        $collateral->update(['status' => $data['status'], 'verified_by' => $request->user()->id, 'verified_at' => now()]);
        $this->audit->log('collateral.status', $collateral, ['status' => $old], ['status' => $data['status']]);

        return back()->with('success', 'Estado da garantia actualizado.');
    }

    public function guarantorStatus(Request $request, Guarantor $guarantor): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['declared', 'verified', 'rejected'])]]);
        $old = $guarantor->status;
        $guarantor->update(['status' => $data['status']]);
        $this->audit->log('guarantor.status', $guarantor, ['status' => $old], ['status' => $data['status']]);

        return back()->with('success', 'Estado do fiador actualizado.');
    }
}
