<?php

namespace App\Support;

/**
 * Catálogo de permissões e matriz de perfis.
 *
 * Segregação de funções: o Super Administrador gere configuração técnica, utilizadores e
 * permissões, mas NÃO recebe permissões de aprovação de crédito, desembolso ou estorno.
 * Não existe "bypass" global (Gate::before) — todas as permissões são verificadas.
 */
final class Permissions
{
    public const ALL = [
        'dashboard.admin' => 'Aceder ao painel administrativo',

        'clients.view' => 'Consultar clientes',
        'clients.create' => 'Registar clientes',
        'clients.update' => 'Editar clientes',
        'clients.export' => 'Exportar lista de clientes',
        'clients.documents.verify' => 'Verificar documentos e identificação (KYC)',

        'staff.manage' => 'Gerir funcionários',
        'roles.manage' => 'Gerir perfis e permissões',

        'products.view' => 'Consultar produtos de crédito',
        'products.manage' => 'Gerir produtos de crédito',

        'applications.view' => 'Consultar pedidos de crédito',
        'applications.review' => 'Analisar pedidos (avaliação, pedidos de informação, encaminhamento)',
        'applications.approve' => 'Aprovar ou rejeitar pedidos',

        'loans.view' => 'Consultar empréstimos',
        'loans.formalize' => 'Formalizar contratos',
        'loans.restructure' => 'Reestruturar empréstimos',
        'loans.cancel' => 'Cancelar empréstimos não desembolsados',

        'disbursements.view' => 'Consultar desembolsos',
        'disbursements.request' => 'Registar pedidos de desembolso',
        'disbursements.authorize' => 'Autorizar desembolsos',
        'disbursements.execute' => 'Confirmar a execução de desembolsos',

        'payments.view' => 'Consultar pagamentos',
        'payments.register' => 'Registar pagamentos',
        'payments.confirm' => 'Confirmar ou rejeitar pagamentos',
        'payments.reverse' => 'Estornar pagamentos',
        'payments.reconcile' => 'Reconciliar pagamentos',
        'penalties.waive' => 'Dispensar penalizações',

        'collections.view' => 'Consultar cobranças',
        'collections.manage' => 'Gerir cobranças (contactos, promessas, atribuição)',

        'reports.view' => 'Consultar relatórios',
        'reports.export' => 'Exportar relatórios (PDF/Excel)',

        'audit.view' => 'Consultar auditoria',
        'settings.manage' => 'Gerir configurações do sistema',
        'content.manage' => 'Gerir conteúdos do website',
        'support.manage' => 'Responder a tickets de suporte',
        'contact_messages.manage' => 'Gerir mensagens de contacto',
    ];

    public const ROLES = [
        'super-admin' => [
            'dashboard.admin', 'staff.manage', 'roles.manage', 'settings.manage', 'content.manage', 'audit.view',
            'products.view', 'reports.view', 'contact_messages.manage',
        ],
        'admin' => [
            'dashboard.admin', 'clients.view', 'clients.create', 'clients.update', 'clients.export',
            'staff.manage', 'products.view', 'products.manage', 'applications.view', 'loans.view', 'loans.cancel',
            'disbursements.view', 'payments.view', 'collections.view', 'reports.view', 'reports.export',
            'audit.view', 'content.manage', 'support.manage', 'contact_messages.manage',
        ],
        'gestor-credito' => [
            'dashboard.admin', 'clients.view', 'clients.update', 'clients.documents.verify', 'products.view',
            'applications.view', 'applications.review', 'applications.approve',
            'loans.view', 'loans.formalize', 'loans.restructure', 'loans.cancel',
            'disbursements.view', 'disbursements.request', 'disbursements.authorize',
            'payments.view', 'penalties.waive', 'collections.view', 'collections.manage',
            'reports.view', 'reports.export',
        ],
        'agente-credito' => [
            'dashboard.admin', 'clients.view', 'clients.create', 'clients.update', 'clients.documents.verify',
            'products.view', 'applications.view', 'applications.review', 'loans.view', 'loans.formalize',
            'disbursements.view', 'disbursements.request', 'payments.view', 'payments.register',
            'collections.view', 'collections.manage', 'support.manage',
        ],
        'caixa' => [
            'dashboard.admin', 'clients.view', 'loans.view', 'disbursements.view', 'disbursements.execute',
            'payments.view', 'payments.register', 'payments.confirm', 'collections.view',
        ],
        'contabilista' => [
            'dashboard.admin', 'clients.view', 'loans.view', 'disbursements.view', 'payments.view',
            'payments.reverse', 'payments.reconcile', 'collections.view', 'reports.view', 'reports.export', 'audit.view',
        ],
        'cliente' => [],
    ];

    /** Permissões que nunca podem ser atribuídas ao perfil super-admin (segregação). */
    public const SUPER_ADMIN_FORBIDDEN = [
        'applications.approve', 'disbursements.authorize', 'disbursements.execute', 'payments.reverse', 'payments.confirm',
    ];
}
