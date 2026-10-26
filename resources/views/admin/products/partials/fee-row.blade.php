<div class="row g-2 mb-2" data-repeater-item>
    <div class="col-md-4"><input class="form-control form-control-sm" name="fees[{{ $i }}][name]" placeholder="Nome (ex.: Comissão de abertura)" value="{{ $fee['name'] ?? '' }}"></div>
    <div class="col-md-2"><select class="form-select form-select-sm" name="fees[{{ $i }}][type]"><option value="percent" @selected(($fee['type'] ?? '') === 'percent')>%</option><option value="fixed" @selected(($fee['type'] ?? '') === 'fixed')>MZN</option></select></div>
    <div class="col-md-2"><input class="form-control form-control-sm" type="number" step="0.0001" min="0" name="fees[{{ $i }}][value]" placeholder="Valor" value="{{ $fee['value'] ?? '' }}"></div>
    <div class="col-md-3"><select class="form-select form-select-sm" name="fees[{{ $i }}][charge]"><option value="upfront" @selected(($fee['charge'] ?? '') === 'upfront')>Deduzido no desembolso</option><option value="financed" @selected(($fee['charge'] ?? '') === 'financed')>Repartido pelas prestações</option></select></div>
    <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove><i class="bi bi-trash"></i></button></div>
</div>
