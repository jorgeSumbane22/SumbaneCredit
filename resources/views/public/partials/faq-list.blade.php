@php($aid = 'faq_'.\Illuminate\Support\Str::random(5))
<div class="accordion" id="{{ $aid }}">
    @foreach ($faqs as $faq)
        <div class="accordion-item">
            <h3 class="accordion-header">
                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }} fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $aid }}_{{ $faq->id }}">{{ $faq->question }}</button>
            </h3>
            <div id="{{ $aid }}_{{ $faq->id }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#{{ $aid }}">
                <div class="accordion-body text-muted">{!! nl2br(e($faq->answer)) !!}</div>
            </div>
        </div>
    @endforeach
</div>
