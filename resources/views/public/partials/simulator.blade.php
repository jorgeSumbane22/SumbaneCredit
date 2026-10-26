{{-- Simulador reutilizável. Parâmetros: $products, $catalog, $url, $selected (opcional), $compact (opcional) --}}
@php($sid = 'sim_'.\Illuminate\Support\Str::random(6))
<div class="row g-4">
    <div class="{{ ($compact ?? false) ? 'col-12' : 'col-lg-5' }}">
        <form data-simulator="#{{ $sid }}" data-url="{{ $url }}" data-products="{{ json_encode($catalog) }}" novalidate>
            @if ($products->count() > 1)
                <x-select name="product_id" label="Produto" :options="$products->pluck('name', 'id')->all()" :value="$selected ?? $products->first()?->id" required id="{{ $sid }}_p" />
            @elseif ($products->count() === 1)
                <input type="hidden" name="product_id" value="{{ $products->first()->id }}">
            @endif
            <div class="small text-muted mb-3" data-product-info></div>
            <x-input name="amount" type="number" label="Montante (MZN)" step="0.01" required id="{{ $sid }}_a" :value="$products->first()?->currentVersion?->min_amount ? (int) max($products->first()->currentVersion->min_amount, min(25000, $products->first()->currentVersion->max_amount)) : null" />
            <x-input name="term" type="number" label="Número de prestações" required id="{{ $sid }}_t" :value="$products->first()?->currentVersion ? min(max(6, $products->first()->currentVersion->min_term), $products->first()->currentVersion->max_term) : null" />
            <div class="row">
                <div class="col-sm-6 sim-optional"><x-select name="frequency" label="Periodicidade" :options="[]" id="{{ $sid }}_f" /></div>
                <div class="col-sm-6 sim-optional"><x-select name="method" label="Método de amortização" :options="[]" id="{{ $sid }}_m" /></div>
            </div>
            <button type="submit" class="btn btn-success w-100" data-no-loading><i class="bi bi-calculator me-1"></i>Simular</button>
        </form>
    </div>
    <div class="{{ ($compact ?? false) ? 'col-12' : 'col-lg-7' }}">
        <div class="card position-relative h-100">
            <div class="loading-overlay"><div class="spinner-border text-success" role="status"><span class="visually-hidden">A calcular…</span></div></div>
            <div class="card-body" id="{{ $sid }}">
                <x-empty-state icon="calculator" title="Resultado da simulação" text="Indique o montante e o número de prestações e carregue em «Simular»." />
            </div>
        </div>
    </div>
</div>
