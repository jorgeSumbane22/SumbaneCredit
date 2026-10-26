<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CollectionCase;
use App\Models\CollectionContact;
use App\Models\PaymentPromise;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Collections\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function __construct(
        private readonly CollectionService $collections,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $estado = $request->query('estado', 'abertos');
        $cases = CollectionCase::with('loan', 'client', 'assignee')
            ->withCount('contacts')
            ->with(['promises' => fn ($q) => $q->where('status', 'pending')])
            ->when($estado === 'abertos', fn ($q) => $q->open())
            ->when($estado !== 'abertos' && $estado !== 'todos', fn ($q) => $q->where('status', $estado))
            ->when($request->query('responsavel') === 'eu', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($request->query('responsavel') === 'sem', fn ($q) => $q->whereNull('assigned_to'))
            ->when($request->query('escalao'), function ($q, $b) {
                [$min, $max] = match ($b) { '30' => [1, 30], '60' => [31, 60], '90' => [61, 90], default => [91, 100000] };
                $q->whereBetween('days_past_due', [$min, $max]);
            })
            ->when($request->query('acao') === 'hoje', fn ($q) => $q->where('next_action_at', '<=', now()->endOfDay()))
            ->when($request->query('q'), fn ($q, $t) => $q->whereHas('loan', fn ($l) => $l->search($t)))
            ->orderByDesc('days_past_due')
            ->paginate(25)->withQueryString();

        return view('admin.collections.index', [
            'cases' => $cases,
            'estado' => $estado,
            'stats' => [
                'open' => CollectionCase::open()->count(),
                'amount' => (string) CollectionCase::open()->sum('overdue_amount'),
                'today' => CollectionCase::open()->where('next_action_at', '<=', now()->endOfDay())->count(),
                'promises' => PaymentPromise::where('status', 'pending')->count(),
                'broken' => PaymentPromise::where('status', 'broken')->where('resolved_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function show(CollectionCase $collection): View
    {
        $collection->load('loan.client.address', 'loan.guarantors', 'assignee', 'contacts.user', 'promises.creator');

        return view('admin.collections.show', [
            'case' => $collection,
            'overdue' => $collection->loan->currentInstallments()->whereIn('status', ['pending', 'partial'])->whereDate('due_date', '<', today())->get(),
            'agents' => User::permission('collections.manage')->where('status', 'active')->where('is_staff', true)->orderBy('name')->get(),
        ]);
    }

    public function contact(Request $request, CollectionCase $collection): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(array_keys(CollectionContact::CHANNELS))],
            'outcome' => ['required', Rule::in(array_keys(CollectionContact::OUTCOMES))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'contacted_at' => ['required', 'date', 'before_or_equal:now'],
            'next_action_at' => ['nullable', 'date', 'after_or_equal:today'],
            'promised_amount' => ['nullable', 'required_if:outcome,promise', 'numeric', 'min:1'],
            'promised_date' => ['nullable', 'required_if:outcome,promise', 'date', 'after_or_equal:today'],
        ], [], ['promised_amount' => 'valor prometido', 'promised_date' => 'data prometida']);
        $this->collections->logContact($collection, $request->user(), $data);

        return back()->with('success', 'Contacto registado.');
    }

    public function promise(Request $request, CollectionCase $collection): RedirectResponse
    {
        $data = $request->validate([
            'promised_amount' => ['required', 'numeric', 'min:1'],
            'promised_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->collections->createPromise($collection, $request->user(), (string) $data['promised_amount'], $data['promised_date'], $data['notes'] ?? null);

        return back()->with('success', 'Promessa de pagamento registada.');
    }

    public function assign(Request $request, CollectionCase $collection): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer', 'exists:users,id']]);
        $assignee = $data['user_id'] ? User::where('is_staff', true)->findOrFail($data['user_id']) : null;
        $this->collections->assign($collection, $assignee, $request->user());

        return back()->with('success', 'Responsável actualizado.');
    }

    public function close(Request $request, CollectionCase $collection): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->collections->close($collection, $request->user(), $data['reason']);

        return back()->with('success', 'Processo encerrado. Se o atraso persistir, será reaberto automaticamente.');
    }

    public function cancelPromise(Request $request, PaymentPromise $promise): RedirectResponse
    {
        abort_unless($promise->status === 'pending', 422);
        $promise->update(['status' => 'cancelled', 'resolved_at' => now()]);
        if ($promise->collection->status === 'promised') {
            $promise->collection->update(['status' => 'open']);
        }
        $this->audit->log('collection.promise_cancelled', $promise);

        return back()->with('success', 'Promessa cancelada.');
    }
}
