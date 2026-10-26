<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Services\AuditService;
use App\Services\ClientService;
use App\Services\DocumentStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentStorageService $storage,
        private readonly ClientService $clients,
        private readonly AuditService $audit,
    ) {}

    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ClientDocument::TYPES))],
            'file' => ['required', ...explode('|', DocumentStorageService::validationRule())],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $this->storage->storeClientDocument($client, $request->file('file'), $data['type'], $data['notes'] ?? null);

        return back()->with('success', 'Documento carregado.');
    }

    public function download(ClientDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);
        $this->audit->log('document.downloaded', $document, null, null, 'Download de documento do cliente '.$document->client_id);

        return $this->storage->download($document->path, $document->original_name, $document->disk);
    }

    public function review(Request $request, ClientDocument $document): RedirectResponse
    {
        $this->authorize('verify', $document);
        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
            'reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:255'],
        ], ['reason.required_if' => 'Indique o motivo da rejeição.']);

        $this->clients->reviewDocument($document, $request->user(), $data['status'], $data['reason'] ?? null);

        return back()->with('success', $data['status'] === 'verified' ? 'Documento verificado.' : 'Documento rejeitado.');
    }
}
