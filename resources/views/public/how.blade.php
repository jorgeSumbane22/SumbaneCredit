@extends('layouts.public')
@section('title', 'Como funciona')

@section('content')
@include('public.partials.page-hero', ['title' => 'Como funciona', 'subtitle' => 'Todo o percurso do seu crédito, explicado.'])
<section class="section">
    <div class="container" style="max-width:900px">
        <ul class="timeline mb-5">
            @foreach ([
                ['Registo e confirmação do email', 'Crie a sua conta com os seus dados de identificação e confirme o endereço de email.'],
                ['Perfil e documentos', 'Complete o perfil (morada, actividade profissional, rendimentos) e carregue os documentos solicitados, que ficam guardados de forma privada.'],
                ['Simulação e pedido', 'Escolha o produto, simule e confira a prestação, os juros, os encargos e o calendário. Submeta o pedido com a declaração de veracidade.'],
                ['Análise', 'A equipa verifica os documentos e a capacidade de pagamento. Pode ser pedida informação adicional — é notificado e responde no portal.'],
                ['Decisão', 'A decisão é tomada por um gestor autorizado. Recebe a notificação de aprovação ou não aprovação com o respectivo motivo.'],
                ['Contrato', 'Após aprovação, a minuta do contrato fica disponível para consulta. A formalização é feita com a instituição.'],
                ['Desembolso', 'Os fundos são transferidos pelo método acordado. O plano de prestações começa a contar a partir da data do desembolso.'],
                ['Pagamentos', 'Pague por M-Pesa, e-Mola, transferência, depósito ou numerário. Pode submeter o comprovativo no portal; o pagamento é confirmado pela instituição e o recibo fica disponível.'],
            ] as [$title, $text])
                <li><span class="dot"></span><h5 class="fw-bold text-navy mb-1">{{ $title }}</h5><p class="text-muted">{{ $text }}</p></li>
            @endforeach
        </ul>
        @if ($content)
            <div class="card"><div class="card-body prose">{!! $content !!}</div></div>
        @endif
        <div class="text-center mt-4"><a href="{{ route('register') }}" class="btn btn-success btn-lg">Começar agora</a></div>
    </div>
</section>
@endsection
