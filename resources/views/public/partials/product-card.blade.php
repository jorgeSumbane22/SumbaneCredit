@php($v = $product->currentVersion)
<div class="product-card">
    <div class="product-head">
        <div class="small opacity-75">{{ $product->code }}</div>
        <h4 class="fw-bold mb-1">{{ $product->name }}</h4>
        <div class="fs-5 fw-semibold">{{ $v->rateLabel() }}</div>
    </div>
    <div class="product-body">
        <p class="text-muted small">{{ \Illuminate\Support\Str::limit($product->description, 160) }}</p>
        <ul class="list-unstyled small mb-3">
            <li class="mb-1"><i class="bi bi-cash text-success me-2"></i>@money($v->min_amount) a @money($v->max_amount)</li>
            <li class="mb-1"><i class="bi bi-calendar3 text-success me-2"></i>{{ $v->min_term }} a {{ $v->max_term }} prestações · {{ collect($v->frequencies())->map->label()->implode(', ') }}</li>
            <li class="mb-1"><i class="bi bi-receipt text-success me-2"></i>{{ \Illuminate\Support\Str::limit($v->feesLabel(), 90) }}</li>
        </ul>
        <div class="mt-auto d-flex gap-2">
            <a href="{{ route('public.product', $product->slug) }}" class="btn btn-outline-primary btn-sm flex-fill">Detalhes</a>
            <a href="{{ route('public.simulator', ['produto' => $product->id]) }}" class="btn btn-outline-success btn-sm flex-fill">Simular</a>
            <a href="{{ auth()->check() ? route('client.applications.create', ['produto' => $product->id]) : route('register') }}" class="btn btn-success btn-sm flex-fill">Solicitar</a>
        </div>
    </div>
</div>
