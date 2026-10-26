<div class="border rounded p-3 mb-2 bg-light" data-repeater-item>
    <div class="row g-2">
        <div class="col-md-6"><input class="form-control form-control-sm" name="guarantors[{{ $i }}][full_name]" placeholder="Nome completo *" value="{{ $g['full_name'] ?? '' }}"></div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="guarantors[{{ $i }}][document_type]">
                @foreach (\App\Models\Client::DOCUMENT_TYPES as $k => $v)<option value="{{ $k }}" @selected(($g['document_type'] ?? 'bi') === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="guarantors[{{ $i }}][document_number]" placeholder="N.º documento *" value="{{ $g['document_number'] ?? '' }}"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="guarantors[{{ $i }}][phone]" placeholder="Telefone *" value="{{ $g['phone'] ?? '' }}"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="guarantors[{{ $i }}][relationship]" placeholder="Relação" value="{{ $g['relationship'] ?? '' }}"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" name="guarantors[{{ $i }}][occupation]" placeholder="Profissão" value="{{ $g['occupation'] ?? '' }}"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="guarantors[{{ $i }}][monthly_income]" placeholder="Rendimento (MZN)" value="{{ $g['monthly_income'] ?? '' }}"></div>
        <div class="col-md-10"><input class="form-control form-control-sm" name="guarantors[{{ $i }}][address]" placeholder="Morada" value="{{ $g['address'] ?? '' }}"></div>
        <div class="col-md-2 text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove><i class="bi bi-trash"></i></button></div>
    </div>
</div>
