{{-- Filtro de período reutilizável. Parâmetros: $from, $to, $preset, $extra (slot opcional de campos) --}}
<form method="GET" class="filter-bar row g-2 align-items-end">
    @foreach (request()->except(['periodo', 'de', 'ate', 'page']) as $k => $v)
        @if (is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
    @endforeach
    <div class="col-sm-4 col-md-3">
        <label class="form-label">Período</label>
        <select name="periodo" class="form-select" onchange="this.form.querySelectorAll('.custom-dates').forEach(e => e.classList.toggle('d-none', this.value !== 'custom'))">
            @foreach (\App\Support\Period::PRESETS as $k => $v)<option value="{{ $k }}" @selected(($preset ?? '') === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    <div class="col-sm-4 col-md-2 custom-dates {{ ($preset ?? '') === 'custom' ? '' : 'd-none' }}"><label class="form-label">De</label><input type="date" name="de" class="form-control" value="{{ $from->toDateString() }}"></div>
    <div class="col-sm-4 col-md-2 custom-dates {{ ($preset ?? '') === 'custom' ? '' : 'd-none' }}"><label class="form-label">Até</label><input type="date" name="ate" class="form-control" value="{{ $to->toDateString() }}"></div>
    {{ $slot ?? '' }}
    <div class="col-auto"><button class="btn btn-primary" data-no-loading><i class="bi bi-funnel me-1"></i>Aplicar</button></div>
    <div class="col-auto small text-muted pb-2">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</div>
</form>
