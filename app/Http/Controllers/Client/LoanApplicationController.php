<?php

namespace App\Http\Controllers\Client;

use App\Enums\ApplicationStatus;
use App\Http\Requests\LoanApplicationRequest;
use App\Http\Requests\SimulationRequest;
use App\Models\ClientDocument;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\Credit\LoanApplicationService;
use App\Services\Credit\LoanSimulationService;
use App\Services\DocumentStorageService;
use App\Support\ProductCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanApplicationController extends ClientController
{
    public function __construct(
        private readonly LoanApplicationService $applications,
        private readonly LoanSimulationService $simulation,
    ) {}

    public function index(Request $request): View
    {
        $applications = $this->client()->applications()->with('product', 'loan')
            ->when($request->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->paginate(10)->withQueryString();

        return view('client.applications.index', ['applications' => $applications, 'status' => $request->query('estado')]);
    }

    public function create(Request $request): View
    {
        $client = $this->client()->load('employment', 'address');
        $products = LoanProduct::active()->with('currentVersion')->orderBy('name')->get();

        return view('client.applications.create', [
            'client' => $client,
            'products' => $products,
            'catalog' => ProductCatalog::simulatorData($products),
            'selected' => $request->query('produto'),
            'documents' => $client->documents()->where('status', '!=', 'rejected')->get(),
            'openApplication' => $client->applications()->open()->first(),
            'action' => route('client.applications.store'),
            'simulateUrl' => route('client.applications.simulate'),
        ]);
    }

    public function simulate(SimulationRequest $request): JsonResponse
    {
        $product = LoanProduct::active()->with('currentVersion.product')->findOrFail($request->integer('product_id'));
        $result = $this->simulation->simulate($product->currentVersion, (string) $request->input('amount'), $request->integer('term'),
            $request->input('frequency'), $request->input('method'));

        return response()->json($this->simulation->toJson($result));
    }

    public function store(LoanApplicationRequest $request): RedirectResponse
    {
        $application = $this->applications->submit($this->client(), $request->payload(), $request->user());

        return redirect()->route('client.applications.show', $application)->with('success', 'Pedido submetido com sucesso. Será notificado sobre a análise.');
    }

    public function show(LoanApplication $application): View
    {
        $this->authorize('view', $application);
        $application->load('product', 'productVersion', 'reviews.user', 'documents', 'guarantors', 'collaterals', 'loan');

        return view('client.applications.show', [
            'application' => $application,
            'timeline' => $application->reviews->filter->isClientVisible(),
        ]);
    }

    public function cancel(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->authorize('cancel', $application);
        $this->applications->cancel($application, $request->user(), $request->input('reason'));

        return back()->with('success', 'Pedido cancelado.');
    }

    public function provideInformation(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->authorize('provideInformation', $application);
        $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'documents' => ['array', 'max:6'],
            'documents.*.type' => ['required_with:documents.*.file', Rule::in(array_keys(ClientDocument::TYPES))],
            'documents.*.file' => ['nullable', ...explode('|', DocumentStorageService::validationRule())],
        ]);

        $files = collect($request->file('documents', []))
            ->map(fn ($d, $i) => ['type' => $request->input("documents.{$i}.type", 'other'), 'file' => $d['file'] ?? null])
            ->filter(fn ($d) => $d['file'])->values()->all();

        $this->applications->provideInformation($application, $request->user(), $request->input('message'), $files);

        return back()->with('success', 'Informação enviada. O pedido voltou para análise.');
    }
}
