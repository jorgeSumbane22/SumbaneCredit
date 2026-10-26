@extends('layouts.client')
@section('title', 'Documentos')

@section('content')
<x-page-header title="Os meus documentos" subtitle="Documentos guardados de forma privada; apenas o cliente e funcionários autorizados lhes acedem." :breadcrumbs="['Início' => route('client.dashboard'), 'Documentos' => null]" />

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Carregar documento</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('client.documents.store') }}" enctype="multipart/form-data">
                    @csrf
                    <x-select name="type" label="Tipo de documento" :options="\App\Models\ClientDocument::TYPES" placeholder="Seleccione" required />
                    <x-input name="file" type="file" label="Ficheiro" required accept=".pdf,.jpg,.jpeg,.png,.webp" :help="'PDF, JPG, PNG ou WEBP, até '.number_format($maxKb / 1024, 0).' MB.'" />
                    <x-input name="notes" label="Observações" maxlength="255" />
                    <button class="btn btn-success w-100" type="submit"><i class="bi bi-upload me-1"></i>Carregar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Documento</th><th>Ficheiro</th><th>Data</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($documents as $doc)
                        <tr>
                            <td class="fw-semibold">{{ $doc->typeLabel() }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($doc->original_name, 30) }}<div class="text-muted">{{ $doc->humanSize() }}</div></td>
                            <td class="small">@datetime($doc->created_at)</td>
                            <td>
                                <x-badge :label="$doc->statusLabel()" :color="$doc->statusColor()" />
                                @if ($doc->status === 'rejected')<div class="small text-danger mt-1">{{ $doc->rejection_reason }}</div>@endif
                            </td>
                            <td class="text-end"><a href="{{ route('client.documents.download', $doc) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="folder2-open" title="Ainda não carregou documentos" text="Carregue o documento de identificação e um comprovativo de rendimento." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $documents->links() }}</div>
    </div>
</div>
@endsection
