<?php

namespace App\Http\Controllers\Client;

use App\Models\ClientDocument;
use App\Services\DocumentStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends ClientController
{
    public function __construct(private readonly DocumentStorageService $storage) {}

    public function index(): View
    {
        return view('client.documents', [
            'documents' => $this->client()->documents()->with('verifier')->paginate(15),
            'maxKb' => (int) app(\App\Services\SettingsService::class)->get('uploads.max_kb'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ClientDocument::TYPES))],
            'file' => ['required', ...explode('|', DocumentStorageService::validationRule())],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['type' => 'tipo de documento', 'file' => 'ficheiro']);

        $this->storage->storeClientDocument($this->client(), $request->file('file'), $data['type'], $data['notes'] ?? null);

        return back()->with('success', 'Documento carregado. Será verificado pela nossa equipa.');
    }

    public function download(ClientDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        return $this->storage->download($document->path, $document->original_name, $document->disk);
    }
}
