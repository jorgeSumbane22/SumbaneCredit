<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('public.contact');
    }

    public function store(Request $request): RedirectResponse
    {
        // Campo "website" invisível: protecção simples contra robôs (honeypot).
        if ($request->filled('website')) {
            return back()->with('success', 'Mensagem enviada. Entraremos em contacto brevemente.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'É necessário autorizar o tratamento dos dados para responder à mensagem.']);

        unset($data['consent']);
        ContactMessage::create($data + ['status' => 'new', 'ip_address' => $request->ip()]);

        return redirect()->route('public.contact')->with('success', 'Mensagem enviada. Entraremos em contacto brevemente.');
    }
}
