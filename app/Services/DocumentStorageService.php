<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Armazenamento privado de ficheiros enviados.
 *
 * - Disco "local" (storage/app/private), fora de public/: nunca acessível por URL directo.
 * - Nome aleatório com extensão derivada do tipo MIME real (não do nome do cliente).
 * - Apenas PDF/JPG/PNG/WEBP; os ficheiros são servidos como download (Content-Disposition)
 *   com X-Content-Type-Options: nosniff, pelo que nunca são executados pelo servidor.
 */
class DocumentStorageService
{
    public const ALLOWED_MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function validationRule(): string
    {
        $max = (int) app(SettingsService::class)->get('uploads.max_kb', '5120');

        return 'file|mimes:'.implode(',', self::ALLOWED_MIMES).'|mimetypes:'.implode(',', array_keys(self::MIME_EXTENSIONS)).'|max:'.$max;
    }

    public function store(UploadedFile $file, string $directory): array
    {
        $mime = $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mime] ?? 'bin';
        $name = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $name, 'local');

        return [
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr(preg_replace('/[^\pL\pN\.\-_ ]/u', '_', $file->getClientOriginalName()), 0, 200),
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    public function storeClientDocument(Client $client, UploadedFile $file, string $type, ?string $notes = null): ClientDocument
    {
        $stored = $this->store($file, "clients/{$client->id}/documents");

        $document = $client->documents()->create($stored + [
            'type' => $type,
            'status' => 'pending',
            'notes' => $notes,
            'uploaded_by' => Auth::id(),
        ]);

        app(AuditService::class)->log('document.uploaded', $document, null, [
            'client_id' => $client->id, 'type' => $type, 'checksum' => $stored['checksum'],
        ], 'Documento carregado: '.$document->typeLabel());

        return $document;
    }

    public function download(string $path, string $downloadName, string $disk = 'local'): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404, 'Ficheiro não encontrado.');

        $response = Storage::disk($disk)->download($path, $downloadName);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'");

        return $response;
    }
}
