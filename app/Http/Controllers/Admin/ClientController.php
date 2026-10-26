<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientProfileRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Services\AuditService;
use App\Services\ClientService;
use App\Services\Reports\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService $clients,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $clients = Client::query()->with('address')
            ->withCount(['loans as active_loans_count' => fn ($q) => $q->where('status', 'active')])
            ->search($request->query('q'))
            ->when($request->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('kyc'), fn ($q, $s) => $q->where('kyc_status', $s))
            ->when($request->query('provincia'), fn ($q, $p) => $q->whereHas('address', fn ($a) => $a->where('province', $p)))
            ->when($request->query('situacao') === 'com-emprestimo', fn ($q) => $q->whereHas('loans', fn ($l) => $l->where('status', 'active')))
            ->when($request->query('situacao') === 'em-atraso', fn ($q) => $q->whereHas('loans', fn ($l) => $l->where('status', 'active')->where('days_past_due', '>', 0)))
            ->latest()
            ->paginate(20)->withQueryString();

        return view('admin.clients.index', ['clients' => $clients]);
    }

    public function create(): View
    {
        return view('admin.clients.form', ['client' => null]);
    }

    public function store(ClientProfileRequest $request): RedirectResponse
    {
        $request->validate(['email' => [$request->boolean('create_account') ? 'required' : 'nullable', 'email', Rule::unique('users', 'email')]]);
        $client = $this->clients->create($request->validated(), $request->user(), $request->boolean('create_account'));

        return redirect()->route('admin.clients.show', $client)->with('success', 'Cliente registado com o n.º '.$client->client_number.'.');
    }

    public function show(Client $client): View
    {
        $client->load('address', 'employment', 'user', 'documents.verifier', 'applications.product', 'loans.product', 'tickets');
        $this->audit->log('client.viewed', $client, null, null, 'Consulta da ficha do cliente');

        return view('admin.clients.show', [
            'client' => $client,
            'payments' => $client->payments()->with('loan')->limit(15)->get(),
            'summary' => [
                'outstanding' => (string) $client->loans()->where('status', 'active')->sum('total_outstanding'),
                'principal' => (string) $client->loans()->where('status', 'active')->sum('principal_outstanding'),
                'paid' => (string) $client->payments()->where('status', PaymentStatus::Confirmed)->sum('amount'),
                'max_dpd' => (int) $client->loans()->max('days_past_due'),
            ],
            'history' => AuditLog::with('user')->where('auditable_type', $client->getMorphClass())->where('auditable_id', $client->id)
                ->where('action', '!=', 'client.viewed')->latest('id')->limit(20)->get(),
        ]);
    }

    public function edit(Client $client): View
    {
        return view('admin.clients.form', ['client' => $client->load('address', 'employment')]);
    }

    public function update(ClientProfileRequest $request, Client $client): RedirectResponse
    {
        $this->clients->update($client, $request->validated());

        return redirect()->route('admin.clients.show', $client)->with('success', 'Dados do cliente actualizados.');
    }

    public function status(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Client::STATUSES))], 'reason' => ['required', 'string', 'max:500']]);
        $this->clients->setStatus($client, $data['status'], $data['reason']);

        return back()->with('success', 'Estado da conta alterado para '.Client::STATUSES[$data['status']].'.');
    }

    public function kyc(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate(['kyc_status' => ['required', Rule::in(['verified', 'rejected', 'pending'])], 'reason' => ['nullable', 'string', 'max:500']]);
        $this->clients->setKycStatus($client, $request->user(), $data['kyc_status'], $data['reason'] ?? null);

        return back()->with('success', 'Estado da identificação actualizado.');
    }

    public function export(Request $request, ReportService $reports): BinaryFileResponse
    {
        $this->audit->log('client.exported', null, null, ['filters' => $request->query()], 'Exportação da lista de clientes');

        return $reports->excel('clients', $request);
    }
}
