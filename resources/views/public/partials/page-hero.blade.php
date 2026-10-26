<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('public.home') }}" style="color:#c9d5e6">Início</a></li><li class="breadcrumb-item active text-white">{{ $title }}</li></ol></nav>
        <h1>{{ $title }}</h1>
        @isset($subtitle)<p>{{ $subtitle }}</p>@endisset
    </div>
</section>
