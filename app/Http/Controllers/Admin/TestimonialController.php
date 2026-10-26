<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TestimonialController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(): View
    {
        return view('admin.content.testimonials', ['testimonials' => Testimonial::latest()->get()]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:1000'],
            'is_demo' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'consent' => ['nullable', 'boolean'],
        ]);
        // Testemunhos reais exigem consentimento do autor; sem confirmação ficam marcados como demonstração.
        $isDemo = $request->boolean('is_demo') || ! $request->boolean('consent');

        return ['name' => $data['name'], 'role' => $data['role'] ?? null, 'content' => $data['content'], 'is_demo' => $isDemo, 'is_active' => $request->boolean('is_active')];
    }

    public function store(Request $request): RedirectResponse
    {
        $t = Testimonial::create($this->validated($request));
        $this->audit->log('content.testimonial_created', $t);

        return back()->with('success', 'Testemunho guardado.');
    }

    public function update(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $testimonial->update($this->validated($request));
        $this->audit->log('content.testimonial_updated', $testimonial);

        return back()->with('success', 'Testemunho actualizado.');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $this->audit->log('content.testimonial_deleted', $testimonial, $testimonial->toArray());
        $testimonial->delete();

        return back()->with('success', 'Testemunho removido.');
    }
}
