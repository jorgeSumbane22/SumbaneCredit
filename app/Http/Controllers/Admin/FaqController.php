<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(): View
    {
        return view('admin.content.faqs', ['faqs' => Faq::orderBy('sort_order')->get()]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    public function store(Request $request): RedirectResponse
    {
        $faq = Faq::create($this->validated($request));
        $this->audit->log('content.faq_created', $faq);

        return back()->with('success', 'Pergunta adicionada.');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validated($request));
        $this->audit->log('content.faq_updated', $faq);

        return back()->with('success', 'Pergunta actualizada.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $this->audit->log('content.faq_deleted', $faq, $faq->toArray());
        $faq->delete();

        return back()->with('success', 'Pergunta removida.');
    }
}
