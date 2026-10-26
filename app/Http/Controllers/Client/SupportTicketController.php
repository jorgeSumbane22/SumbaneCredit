<?php

namespace App\Http\Controllers\Client;

use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Services\DocumentStorageService;
use App\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends ClientController
{
    public function __construct(
        private readonly SupportService $support,
        private readonly DocumentStorageService $storage,
    ) {}

    public function index(): View
    {
        return view('client.tickets.index', ['tickets' => $this->client()->tickets()->paginate(15)]);
    }

    public function create(): View
    {
        return view('client.tickets.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'attachment' => ['nullable', ...explode('|', DocumentStorageService::validationRule())],
        ], [], ['subject' => 'assunto', 'category' => 'categoria', 'message' => 'mensagem']);

        $ticket = $this->support->open($this->client(), $request->user(), $data, $request->file('attachment'));

        return redirect()->route('client.tickets.show', $ticket)->with('success', 'Pedido de suporte registado.');
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        return view('client.tickets.show', ['ticket' => $ticket->load('replies.user')]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('reply', $ticket);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', ...explode('|', DocumentStorageService::validationRule())],
        ]);
        $this->support->reply($ticket, $request->user(), $data['message'], $request->file('attachment'));

        return back()->with('success', 'Mensagem enviada.');
    }

    public function attachment(TicketReply $reply): StreamedResponse
    {
        $this->authorize('view', $reply->ticket);
        abort_unless($reply->attachment_path, 404);

        return $this->storage->download($reply->attachment_path, $reply->attachment_name ?? 'anexo');
    }
}
