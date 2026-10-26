<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): View
    {
        $messages = ContactMessage::with('handler')
            ->when($request->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%")->orWhere('subject', 'like', "%{$t}%")))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.messages.index', ['messages' => $messages]);
    }

    public function show(ContactMessage $message): View
    {
        if ($message->status === 'new') {
            $message->update(['status' => 'read', 'handled_by' => auth()->id(), 'handled_at' => now()]);
        }

        return view('admin.messages.show', ['message' => $message->load('handler')]);
    }

    public function update(Request $request, ContactMessage $message): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ContactMessage::STATUSES))],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $message->update($data + ['handled_by' => $request->user()->id, 'handled_at' => now()]);
        $this->audit->log('contact_message.updated', $message, null, ['status' => $data['status']]);

        return back()->with('success', 'Mensagem actualizada.');
    }
}
