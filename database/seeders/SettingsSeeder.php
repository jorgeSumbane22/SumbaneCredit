<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\SystemSetting;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Definições e conteúdos iniciais. Os dados institucionais ficam em branco (a instituição
 * preenche a informação legal real). Os textos são modelos editáveis no painel.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $content = [
            'content.about' => '<p>O SumbaneCredit é uma plataforma de gestão de microcrédito que permite à instituição atender os seus clientes de forma rápida, transparente e próxima — desde a simulação e o pedido até à liquidação do empréstimo.</p><p><em>Texto de exemplo: personalize em Definições › Conteúdos do website.</em></p>',
            'content.mission' => 'Facilitar o acesso a crédito responsável, com condições claras e acompanhamento próximo de cada cliente. (Texto de exemplo)',
            'content.vision' => 'Ser uma referência em microcrédito digital, transparente e centrado nas pessoas. (Texto de exemplo)',
            'content.values' => "Transparência\nResponsabilidade\nProximidade\nConfidencialidade\nRigor",
            'content.how_it_works' => '<p>Antes de submeter o pedido, simule as condições e confirme que a prestação se ajusta ao seu orçamento. Mantenha os seus contactos actualizados para receber avisos de vencimento.</p>',
            'content.privacy_policy' => <<<'HTML'
<p><strong>Modelo de referência — deve ser revisto e validado juridicamente pela instituição antes da publicação, à luz da legislação moçambicana aplicável.</strong></p>
<h2>1. Responsável pelo tratamento</h2><p>A instituição identificada no rodapé deste website é responsável pelo tratamento dos dados pessoais recolhidos através da plataforma.</p>
<h2>2. Dados recolhidos</h2><p>Dados de identificação (nome, documento de identificação, NUIT, data de nascimento), contactos, morada, situação profissional e rendimentos, documentos carregados, dados dos pedidos e empréstimos, pagamentos e registos de acesso.</p>
<h2>3. Finalidades</h2><ul><li>Análise e gestão de pedidos de crédito e de contratos;</li><li>Identificação do cliente e cumprimento de obrigações legais, incluindo prevenção do branqueamento de capitais;</li><li>Gestão de pagamentos, cobranças e comunicações sobre o contrato;</li><li>Segurança da plataforma e auditoria.</li></ul>
<h2>4. Conservação</h2><p>Os dados são conservados pelo período necessário às finalidades e aos prazos legais de conservação documental aplicáveis.</p>
<h2>5. Partilha</h2><p>Os dados não são vendidos. Podem ser comunicados a entidades com quem a instituição contrate serviços (ex.: prestadores de pagamento) ou a autoridades, nos termos da lei.</p>
<h2>6. Segurança</h2><p>Os documentos são guardados em armazenamento privado, o acesso é controlado por perfis e permissões e as operações relevantes são auditadas.</p>
<h2>7. Direitos</h2><p>O titular pode pedir o acesso e a rectificação dos seus dados através dos contactos da instituição ou do suporte no portal.</p>
HTML,
            'content.terms' => <<<'HTML'
<p><strong>Modelo de referência — deve ser revisto e validado juridicamente pela instituição antes da publicação.</strong></p>
<h2>1. Objecto</h2><p>Estes termos regulam a utilização do website e do portal do cliente. A utilização da plataforma não constitui, por si só, concessão de crédito.</p>
<h2>2. Simulações</h2><p>As simulações são indicativas e não constituem aprovação nem proposta contratual. As condições finais constam do contrato.</p>
<h2>3. Pedidos de crédito</h2><p>O cliente declara que as informações prestadas são verdadeiras. A instituição pode solicitar informação adicional e decidir sobre a concessão nos termos da sua política de crédito.</p>
<h2>4. Pagamentos</h2><p>Os comprovativos submetidos no portal só são considerados pagamentos depois de confirmados pela instituição.</p>
<h2>5. Conta e segurança</h2><p>O cliente é responsável pela confidencialidade das suas credenciais e deve comunicar qualquer utilização indevida.</p>
HTML,
        ];

        foreach (SettingsService::DEFINITIONS as $key => [$group, $type, $label, $default]) {
            SystemSetting::firstOrCreate(['key' => $key], [
                'group' => $group, 'type' => $type, 'label' => $label, 'value' => $content[$key] ?? $default,
            ]);
        }
        app(SettingsService::class)->flush();

        if (Faq::doesntExist()) {
            $faqs = [
                ['Quem pode pedir crédito?', 'Pessoas maiores de idade com documento de identificação válido e capacidade de pagamento demonstrada. Cada produto pode ter requisitos adicionais, indicados na respectiva página.'],
                ['A simulação garante a aprovação?', 'Não. A simulação é indicativa e utiliza o mesmo cálculo dos contratos, mas a concessão depende da análise do pedido e da decisão de um gestor autorizado.'],
                ['Que documentos preciso?', 'Normalmente o documento de identificação, NUIT, comprovativo de residência e comprovativo de rendimentos. A equipa pode pedir documentos adicionais durante a análise.'],
                ['Como sei em que fase está o meu pedido?', 'No portal, em «Meus pedidos», pode acompanhar cada etapa. Recebe também notificações quando há novidades.'],
                ['Como posso pagar as prestações?', 'Por M-Pesa, e-Mola, transferência ou depósito bancário, ou em numerário no balcão. Depois de pagar, pode submeter o comprovativo no portal; o pagamento é confirmado pela instituição e o recibo fica disponível.'],
                ['Posso liquidar o empréstimo antes do prazo?', 'Sim. O montante de liquidação antecipada é calculado de acordo com as condições do contrato. Contacte a instituição para obter o valor actualizado.'],
                ['O que acontece se me atrasar?', 'Podem ser aplicadas as penalizações previstas no contrato. Se tiver dificuldades, contacte-nos o quanto antes para avaliarmos uma solução.'],
            ];
            foreach ($faqs as $i => [$q, $a]) {
                Faq::create(['question' => $q, 'answer' => $a, 'sort_order' => $i + 1, 'is_active' => true]);
            }
        }
    }
}
