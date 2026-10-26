@extends('layouts.public')
@section('title', 'Contactos')

@section('content')
@include('public.partials.page-hero', ['title' => 'Contactos', 'subtitle' => 'Estamos disponíveis para esclarecer as suas dúvidas.'])
<section class="section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card h-100"><div class="card-body">
                    <h5 class="fw-bold text-navy mb-3">Fale connosco</h5>
                    <ul class="list-unstyled">
                        <li class="mb-3"><i class="bi bi-geo-alt text-success me-2"></i>{{ $site['address'] ?: 'Endereço a publicar pela instituição' }}</li>
                        @if ($site['phone'])<li class="mb-3"><i class="bi bi-telephone text-success me-2"></i>{{ $site['phone'] }}</li>@endif
                        @if ($site['whatsapp'])<li class="mb-3"><i class="bi bi-whatsapp text-success me-2"></i>{{ $site['whatsapp'] }}</li>@endif
                        @if ($site['email'])<li class="mb-3"><i class="bi bi-envelope text-success me-2"></i>{{ $site['email'] }}</li>@endif
                        @if ($site['hours'])<li class="mb-3"><i class="bi bi-clock text-success me-2"></i>{{ $site['hours'] }}</li>@endif
                    </ul>
                    <p class="small text-muted mb-0">Clientes com conta podem também abrir um pedido de suporte no portal.</p>
                </div></div>
            </div>
            <div class="col-lg-8">
                <div class="card"><div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>
                    @endif
                    <x-alerts />
                    <form method="POST" action="{{ route('public.contact.send') }}">
                        @csrf
                        <div class="d-none" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                        <div class="row">
                            <x-input name="name" label="Nome" required col="col-md-6 mb-3" />
                            <x-input name="email" type="email" label="Email" required col="col-md-6 mb-3" />
                            <x-input name="phone" label="Telefone" col="col-md-6 mb-3" />
                            <x-input name="subject" label="Assunto" required col="col-md-6 mb-3" />
                        </div>
                        <x-textarea name="message" label="Mensagem" rows="5" required />
                        <div class="form-check mb-3">
                            <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" name="consent" id="consent" value="1">
                            <label class="form-check-label small" for="consent">Autorizo a utilização dos dados indicados para resposta a este contacto (<a href="{{ route('public.privacy') }}">privacidade</a>).</label>
                        </div>
                        <button class="btn btn-success" type="submit"><i class="bi bi-send me-1"></i>Enviar mensagem</button>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</section>
@endsection
