<div class="border rounded p-3 mb-2 bg-light" data-repeater-item>
    <div class="row g-2">
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="collaterals[{{ $i }}][type]">
                @foreach (\App\Models\Collateral::TYPES as $k => $v)<option value="{{ $k }}" @selected(($c['type'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-5"><input class="form-control form-control-sm" name="collaterals[{{ $i }}][description]" placeholder="Descrição (marca, matrícula, localização…) *" value="{{ $c['description'] ?? '' }}"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="collaterals[{{ $i }}][estimated_value]" placeholder="Valor estimado (MZN) *" value="{{ $c['estimated_value'] ?? '' }}"></div>
        <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove><i class="bi bi-trash"></i></button></div>
    </div>
</div>
