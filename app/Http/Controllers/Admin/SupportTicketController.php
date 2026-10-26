<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\DocumentStorageService;
use App\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportService $support,
        private readonly DocumentStorageService $storage,
    ) {}

    public function index(Request $request): View
    {
        $tickets = SupportTicket::with('client', 'assignee')
            ->when($request->query('estado', 'abertos') === 'abertos', fn ($q) => $q->whereNotIn('status', ['resolved', 'closed']))
            ->when(! in_array($request->query('estado', 'abertos'), ['abertos', 'todos'], true), fn ($q) => $q->where('status', $request->query('estado')))
            ->when($request->query('categoria'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('responsavel') === 'eu', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('ticket_number', 'like', "%{$t}%")->orWhere('subject', 'like', "%{$t}%")->orWhereHas('client', fn ($c) => $c->search($t))))
            ->orderByRaw("FIELD(priority, 'urgent','high','normal','low')")->latest('last_reply_at')
            ->paginate(25)->withQueryString();

        return view('admin.tickets.index', ['tickets' => $tickets]);
    }

    public function show(SupportTicket $ticket): View
    {
        return view('admin.tickets.show', [
            'ticket' => $ticket->load('replies.user', 'client', 'assignee'),
            'agents' => User::permission('support.manage')->where('status', 'active')->where('is_staff', true)->orderBy('name')->get(),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))],
            'attachment' => ['nullable', ...explode('|', DocumentStorageService::validationRule())],
        ]);
        $this->support->reply($ticket, $request->user(), $data['message'], $request->file('attachment'), $data['status'] ?? null);

        return back()->with('success', 'Resposta enviada ao cliente.');
    }

    public function status(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $this->support->updateStatus($ticket, $request->user(), $data['status'], $data['assigned_to'] ?? null);

        return back()->with('success', 'Ticket actualizado.');
    }

    public function attachment(TicketReply $reply): StreamedResponse
    {
        abort_unless($reply->attachment_path, 404);

        return $this->storage->download($reply->attachment_path, $reply->attachment_name ?? 'anexo');
    }
}
