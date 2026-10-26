<?php

use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Client;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicSite\ContactController;
use App\Http\Controllers\PublicSite\PageController;
use App\Http\Controllers\PublicSite\SimulatorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Website público
|--------------------------------------------------------------------------
*/
Route::name('public.')->group(function () {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('/sobre-nos', [PageController::class, 'about'])->name('about');
    Route::get('/produtos', [PageController::class, 'products'])->name('products');
    Route::get('/produtos/{product:slug}', [PageController::class, 'product'])->name('product');
    Route::get('/como-funciona', [PageController::class, 'how'])->name('how');
    Route::get('/perguntas-frequentes', [PageController::class, 'faq'])->name('faq');
    Route::get('/privacidade', [PageController::class, 'privacy'])->name('privacy');
    Route::get('/termos-e-condicoes', [PageController::class, 'terms'])->name('terms');
    Route::get('/simulador', [SimulatorController::class, 'show'])->name('simulator');
    Route::post('/simulador/calcular', [SimulatorController::class, 'calculate'])->middleware('throttle:40,1')->name('simulate');
    Route::get('/contactos', [ContactController::class, 'show'])->name('contact');
    Route::post('/contactos', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.send');
});

/*
|--------------------------------------------------------------------------
| Autenticação
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/registar', [RegisterController::class, 'create'])->name('register');
    Route::post('/registar', [RegisterController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/recuperar-palavra-passe', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/recuperar-palavra-passe', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/redefinir-palavra-passe/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/redefinir-palavra-passe', [PasswordResetController::class, 'update'])->name('password.update');
    Route::get('/dois-factores', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/dois-factores', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/email/verificar', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verificar/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/reenviar', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');

    Route::get('/painel', fn () => auth()->user()->isStaff() ? redirect()->route('admin.dashboard') : redirect()->route('client.dashboard'))->name('home');

    Route::get('/conta/seguranca', [SecurityController::class, 'show'])->name('account.security');
    Route::put('/conta/palavra-passe', [SecurityController::class, 'updatePassword'])->name('account.password');
    Route::delete('/conta/sessoes', [SecurityController::class, 'destroyOtherSessions'])->name('account.sessions.destroy');
    Route::post('/conta/dois-factores', [SecurityController::class, 'startTwoFactor'])->name('account.2fa.start');
    Route::post('/conta/dois-factores/confirmar', [SecurityController::class, 'confirmTwoFactor'])->name('account.2fa.confirm');
    Route::delete('/conta/dois-factores', [SecurityController::class, 'disableTwoFactor'])->name('account.2fa.disable');

    Route::get('/notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notificacoes/ler-todas', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notificacoes/{id}', [NotificationController::class, 'open'])->name('notifications.open');
});

/*
|--------------------------------------------------------------------------
| Portal do cliente
|--------------------------------------------------------------------------
*/
Route::prefix('cliente')->name('client.')->middleware(['auth', 'verified', 'client'])->group(function () {
    Route::get('/', [Client\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/perfil', [Client\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [Client\ProfileController::class, 'update'])->name('profile.update');

    Route::get('/documentos', [Client\DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documentos', [Client\DocumentController::class, 'store'])->middleware('throttle:20,1')->name('documents.store');
    Route::get('/documentos/{document}/descarregar', [Client\DocumentController::class, 'download'])->name('documents.download');

    Route::get('/pedidos', [Client\LoanApplicationController::class, 'index'])->name('applications.index');
    Route::get('/pedidos/novo', [Client\LoanApplicationController::class, 'create'])->name('applications.create');
    Route::post('/pedidos', [Client\LoanApplicationController::class, 'store'])->middleware('throttle:10,1')->name('applications.store');
    Route::post('/pedidos/simular', [Client\LoanApplicationController::class, 'simulate'])->middleware('throttle:60,1')->name('applications.simulate');
    Route::get('/pedidos/{application}', [Client\LoanApplicationController::class, 'show'])->name('applications.show');
    Route::post('/pedidos/{application}/cancelar', [Client\LoanApplicationController::class, 'cancel'])->name('applications.cancel');
    Route::post('/pedidos/{application}/informacao', [Client\LoanApplicationController::class, 'provideInformation'])->name('applications.provide-info');

    Route::get('/emprestimos', [Client\LoanController::class, 'index'])->name('loans.index');
    Route::get('/emprestimos/{loan}', [Client\LoanController::class, 'show'])->name('loans.show');
    Route::get('/emprestimos/{loan}/contrato', [Client\LoanController::class, 'contract'])->name('loans.contract');
    Route::get('/emprestimos/{loan}/plano', [Client\LoanController::class, 'schedule'])->name('loans.schedule');
    Route::get('/emprestimos/{loan}/extracto', [Client\LoanController::class, 'statement'])->name('loans.statement');

    Route::get('/prestacoes', [Client\InstallmentController::class, 'index'])->name('installments.index');

    Route::get('/pagamentos', [Client\PaymentController::class, 'index'])->name('payments.index');
    Route::get('/pagamentos/comprovativo', [Client\PaymentController::class, 'create'])->name('payments.create');
    Route::post('/pagamentos/comprovativo', [Client\PaymentController::class, 'store'])->middleware('throttle:10,1')->name('payments.store');
    Route::get('/pagamentos/{payment}', [Client\PaymentController::class, 'show'])->name('payments.show');
    Route::get('/pagamentos/{payment}/recibo', [Client\PaymentController::class, 'receipt'])->name('payments.receipt');

    Route::get('/suporte', [Client\SupportTicketController::class, 'index'])->name('tickets.index');
    Route::get('/suporte/novo', [Client\SupportTicketController::class, 'create'])->name('tickets.create');
    Route::post('/suporte', [Client\SupportTicketController::class, 'store'])->middleware('throttle:10,1')->name('tickets.store');
    Route::get('/suporte/{ticket}', [Client\SupportTicketController::class, 'show'])->name('tickets.show');
    Route::post('/suporte/{ticket}/responder', [Client\SupportTicketController::class, 'reply'])->middleware('throttle:20,1')->name('tickets.reply');
    Route::get('/suporte/anexos/{reply}', [Client\SupportTicketController::class, 'attachment'])->name('tickets.attachment');
});

/*
|--------------------------------------------------------------------------
| Painel administrativo
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'staff', 'staff.2fa'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Clientes
    Route::middleware('permission:clients.view')->group(function () {
        Route::get('/clientes', [Admin\ClientController::class, 'index'])->name('clients.index');
        Route::get('/clientes/exportar', [Admin\ClientController::class, 'export'])->middleware('permission:clients.export')->name('clients.export');
        Route::get('/clientes/novo', [Admin\ClientController::class, 'create'])->middleware('permission:clients.create')->name('clients.create');
        Route::post('/clientes', [Admin\ClientController::class, 'store'])->middleware('permission:clients.create')->name('clients.store');
        Route::get('/clientes/{client}', [Admin\ClientController::class, 'show'])->name('clients.show');
        Route::get('/clientes/{client}/editar', [Admin\ClientController::class, 'edit'])->middleware('permission:clients.update')->name('clients.edit');
        Route::put('/clientes/{client}', [Admin\ClientController::class, 'update'])->middleware('permission:clients.update')->name('clients.update');
        Route::post('/clientes/{client}/estado', [Admin\ClientController::class, 'status'])->middleware('permission:clients.update')->name('clients.status');
        Route::post('/clientes/{client}/kyc', [Admin\ClientController::class, 'kyc'])->middleware('permission:clients.documents.verify')->name('clients.kyc');
        Route::post('/clientes/{client}/documentos', [Admin\ClientDocumentController::class, 'store'])->middleware('permission:clients.update')->name('clients.documents.store');
        Route::get('/documentos/{document}/descarregar', [Admin\ClientDocumentController::class, 'download'])->name('documents.download');
        Route::post('/documentos/{document}/verificar', [Admin\ClientDocumentController::class, 'review'])->middleware('permission:clients.documents.verify')->name('documents.review');
        Route::get('/clientes/{client}/pedidos/novo', [Admin\LoanApplicationController::class, 'create'])->middleware('permission:applications.review')->name('clients.applications.create');
        Route::post('/clientes/{client}/pedidos', [Admin\LoanApplicationController::class, 'store'])->middleware('permission:applications.review')->name('clients.applications.store');
    });

    // Funcionários, perfis e permissões
    Route::middleware('permission:staff.manage')->group(function () {
        Route::get('/funcionarios', [Admin\StaffController::class, 'index'])->name('staff.index');
        Route::get('/funcionarios/novo', [Admin\StaffController::class, 'create'])->name('staff.create');
        Route::post('/funcionarios', [Admin\StaffController::class, 'store'])->name('staff.store');
        Route::get('/funcionarios/{user}', [Admin\StaffController::class, 'show'])->name('staff.show');
        Route::get('/funcionarios/{user}/editar', [Admin\StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/funcionarios/{user}', [Admin\StaffController::class, 'update'])->name('staff.update');
        Route::post('/funcionarios/{user}/estado', [Admin\StaffController::class, 'toggle'])->name('staff.toggle');
        Route::delete('/funcionarios/{user}/sessoes', [Admin\StaffController::class, 'terminateSessions'])->name('staff.sessions');
    });
    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/perfis', [Admin\RoleController::class, 'index'])->name('roles.index');
        Route::put('/perfis/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');
    });

    // Produtos de crédito
    Route::middleware('permission:products.view')->group(function () {
        Route::get('/produtos', [Admin\LoanProductController::class, 'index'])->name('products.index');
        Route::get('/produtos/novo', [Admin\LoanProductController::class, 'create'])->middleware('permission:products.manage')->name('products.create');
        Route::post('/produtos', [Admin\LoanProductController::class, 'store'])->middleware('permission:products.manage')->name('products.store');
        Route::post('/produtos/simular', [Admin\LoanProductController::class, 'simulate'])->name('products.simulate');
        Route::get('/produtos/{product}', [Admin\LoanProductController::class, 'show'])->name('products.show');
        Route::get('/produtos/{product}/editar', [Admin\LoanProductController::class, 'edit'])->middleware('permission:products.manage')->name('products.edit');
        Route::put('/produtos/{product}', [Admin\LoanProductController::class, 'update'])->middleware('permission:products.manage')->name('products.update');
        Route::post('/produtos/{product}/estado', [Admin\LoanProductController::class, 'toggle'])->middleware('permission:products.manage')->name('products.toggle');
    });

    // Pedidos de crédito
    Route::middleware('permission:applications.view')->group(function () {
        Route::get('/pedidos', [Admin\LoanApplicationController::class, 'index'])->name('applications.index');
        Route::get('/pedidos/{application}', [Admin\LoanApplicationController::class, 'show'])->name('applications.show');
        Route::middleware('permission:applications.review')->group(function () {
            Route::post('/pedidos/{application}/iniciar-analise', [Admin\LoanApplicationController::class, 'startReview'])->name('applications.start-review');
            Route::post('/pedidos/{application}/observacao', [Admin\LoanApplicationController::class, 'note'])->name('applications.note');
            Route::post('/pedidos/{application}/avaliacao', [Admin\LoanApplicationController::class, 'assess'])->name('applications.assess');
            Route::post('/pedidos/{application}/pedir-informacao', [Admin\LoanApplicationController::class, 'requestInformation'])->name('applications.request-info');
            Route::post('/pedidos/{application}/encaminhar', [Admin\LoanApplicationController::class, 'forward'])->name('applications.forward');
            Route::post('/pedidos/{application}/atribuir', [Admin\LoanApplicationController::class, 'assign'])->name('applications.assign');
            Route::post('/pedidos/{application}/cancelar', [Admin\LoanApplicationController::class, 'cancel'])->name('applications.cancel');
            Route::post('/garantias/{collateral}/estado', [Admin\LoanApplicationController::class, 'collateralStatus'])->name('collaterals.status');
            Route::post('/fiadores/{guarantor}/estado', [Admin\LoanApplicationController::class, 'guarantorStatus'])->name('guarantors.status');
        });
        Route::middleware('permission:applications.approve')->group(function () {
            Route::post('/pedidos/{application}/aprovar', [Admin\LoanApplicationController::class, 'approve'])->name('applications.approve');
            Route::post('/pedidos/{application}/rejeitar', [Admin\LoanApplicationController::class, 'reject'])->name('applications.reject');
        });
    });

    // Empréstimos
    Route::middleware('permission:loans.view')->group(function () {
        Route::get('/emprestimos', [Admin\LoanController::class, 'index'])->name('loans.index');
        Route::get('/emprestimos/{loan}', [Admin\LoanController::class, 'show'])->name('loans.show');
        Route::get('/emprestimos/{loan}/contrato', [Admin\LoanController::class, 'contract'])->name('loans.contract');
        Route::get('/emprestimos/{loan}/contrato-assinado', [Admin\LoanController::class, 'signedContract'])->name('loans.signed-contract');
        Route::get('/emprestimos/{loan}/plano', [Admin\LoanController::class, 'schedule'])->name('loans.schedule');
        Route::get('/emprestimos/{loan}/extracto', [Admin\LoanController::class, 'statement'])->name('loans.statement');
        Route::get('/emprestimos/{loan}/liquidacao', [Admin\LoanController::class, 'settlement'])->name('loans.settlement');
        Route::post('/emprestimos/{loan}/liquidacao', [Admin\LoanController::class, 'settle'])->middleware('permission:payments.register')->name('loans.settle');
        Route::post('/emprestimos/{loan}/formalizar', [Admin\LoanController::class, 'formalize'])->middleware('permission:loans.formalize')->name('loans.formalize');
        Route::post('/emprestimos/{loan}/reestruturar', [Admin\LoanController::class, 'restructure'])->middleware('permission:loans.restructure')->name('loans.restructure');
        Route::post('/emprestimos/{loan}/cancelar', [Admin\LoanController::class, 'cancel'])->middleware('permission:loans.cancel')->name('loans.cancel');
        Route::post('/emprestimos/{loan}/reconciliar', [Admin\LoanController::class, 'reconcile'])->middleware('permission:payments.reconcile')->name('loans.reconcile');
        Route::post('/penalizacoes/{penalty}/dispensar', [Admin\LoanController::class, 'waivePenalty'])->middleware('permission:penalties.waive')->name('penalties.waive');
        Route::post('/emprestimos/{loan}/desembolsos', [Admin\DisbursementController::class, 'store'])->middleware('permission:disbursements.request')->name('loans.disbursements.store');
    });

    // Desembolsos
    Route::middleware('permission:disbursements.view')->group(function () {
        Route::get('/desembolsos', [Admin\DisbursementController::class, 'index'])->name('disbursements.index');
        Route::get('/desembolsos/{disbursement}', [Admin\DisbursementController::class, 'show'])->name('disbursements.show');
        Route::get('/desembolsos/{disbursement}/comprovativo', [Admin\DisbursementController::class, 'pdf'])->name('disbursements.pdf');
        Route::get('/desembolsos/{disbursement}/ficheiro', [Admin\DisbursementController::class, 'proof'])->name('disbursements.proof');
        Route::post('/desembolsos/{disbursement}/autorizar', [Admin\DisbursementController::class, 'authorizeDisbursement'])->middleware('permission:disbursements.authorize')->name('disbursements.authorize');
        Route::post('/desembolsos/{disbursement}/executar', [Admin\DisbursementController::class, 'complete'])->middleware('permission:disbursements.execute')->name('disbursements.complete');
        Route::post('/desembolsos/{disbursement}/cancelar', [Admin\DisbursementController::class, 'cancel'])->middleware('permission:disbursements.authorize|disbursements.request')->name('disbursements.cancel');
    });

    // Pagamentos e reconciliação
    Route::middleware('permission:payments.view')->group(function () {
        Route::get('/pagamentos', [Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::get('/pagamentos/novo', [Admin\PaymentController::class, 'create'])->middleware('permission:payments.register')->name('payments.create');
        Route::post('/pagamentos', [Admin\PaymentController::class, 'store'])->middleware('permission:payments.register')->name('payments.store');
        Route::get('/pagamentos/reconciliacao', [Admin\ReconciliationController::class, 'index'])->middleware('permission:payments.reconcile')->name('reconciliation.index');
        Route::post('/pagamentos/reconciliacao', [Admin\ReconciliationController::class, 'store'])->middleware('permission:payments.reconcile')->name('reconciliation.store');
        Route::get('/pagamentos/{payment}', [Admin\PaymentController::class, 'show'])->name('payments.show');
        Route::get('/pagamentos/{payment}/recibo', [Admin\PaymentController::class, 'receipt'])->name('payments.receipt');
        Route::get('/pagamentos/{payment}/comprovativo', [Admin\PaymentController::class, 'proof'])->name('payments.proof');
        Route::post('/pagamentos/{payment}/confirmar', [Admin\PaymentController::class, 'confirm'])->middleware('permission:payments.confirm')->name('payments.confirm');
        Route::post('/pagamentos/{payment}/rejeitar', [Admin\PaymentController::class, 'reject'])->middleware('permission:payments.confirm')->name('payments.reject');
        Route::post('/pagamentos/{payment}/estornar', [Admin\PaymentController::class, 'reverse'])->middleware('permission:payments.reverse')->name('payments.reverse');
    });

    // Cobranças
    Route::middleware('permission:collections.view')->group(function () {
        Route::get('/cobrancas', [Admin\CollectionController::class, 'index'])->name('collections.index');
        Route::get('/cobrancas/{collection}', [Admin\CollectionController::class, 'show'])->name('collections.show');
        Route::middleware('permission:collections.manage')->group(function () {
            Route::post('/cobrancas/{collection}/contactos', [Admin\CollectionController::class, 'contact'])->name('collections.contact');
            Route::post('/cobrancas/{collection}/promessas', [Admin\CollectionController::class, 'promise'])->name('collections.promise');
            Route::post('/cobrancas/{collection}/atribuir', [Admin\CollectionController::class, 'assign'])->name('collections.assign');
            Route::post('/cobrancas/{collection}/encerrar', [Admin\CollectionController::class, 'close'])->name('collections.close');
            Route::post('/promessas/{promise}/cancelar', [Admin\CollectionController::class, 'cancelPromise'])->name('promises.cancel');
        });
    });

    // Relatórios
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/relatorios', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('/relatorios/{type}', [Admin\ReportController::class, 'show'])->name('reports.show');
        Route::get('/relatorios/{type}/pdf', [Admin\ReportController::class, 'pdf'])->middleware('permission:reports.export')->name('reports.pdf');
        Route::get('/relatorios/{type}/excel', [Admin\ReportController::class, 'excel'])->middleware('permission:reports.export')->name('reports.excel');
    });

    // Auditoria
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('/auditoria', [Admin\AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/auditoria/{log}', [Admin\AuditLogController::class, 'show'])->name('audit.show');
    });

    // Configurações e conteúdos
    Route::middleware('permission:settings.manage|content.manage')->group(function () {
        Route::get('/definicoes', [Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::put('/definicoes', [Admin\SettingsController::class, 'update'])->name('settings.update');
    });
    Route::middleware('permission:content.manage')->group(function () {
        Route::get('/conteudos/perguntas', [Admin\FaqController::class, 'index'])->name('faqs.index');
        Route::post('/conteudos/perguntas', [Admin\FaqController::class, 'store'])->name('faqs.store');
        Route::put('/conteudos/perguntas/{faq}', [Admin\FaqController::class, 'update'])->name('faqs.update');
        Route::delete('/conteudos/perguntas/{faq}', [Admin\FaqController::class, 'destroy'])->name('faqs.destroy');
        Route::get('/conteudos/testemunhos', [Admin\TestimonialController::class, 'index'])->name('testimonials.index');
        Route::post('/conteudos/testemunhos', [Admin\TestimonialController::class, 'store'])->name('testimonials.store');
        Route::put('/conteudos/testemunhos/{testimonial}', [Admin\TestimonialController::class, 'update'])->name('testimonials.update');
        Route::delete('/conteudos/testemunhos/{testimonial}', [Admin\TestimonialController::class, 'destroy'])->name('testimonials.destroy');
    });
    Route::middleware('permission:settings.manage|applications.approve')->group(function () {
        Route::get('/regras-avaliacao', [Admin\AssessmentRuleController::class, 'index'])->name('assessment-rules.index');
    });
    Route::middleware('permission:settings.manage')->group(function () {
        Route::post('/regras-avaliacao', [Admin\AssessmentRuleController::class, 'store'])->name('assessment-rules.store');
    });
    Route::middleware('permission:applications.approve')->group(function () {
        Route::post('/regras-avaliacao/{ruleSet}/validar', [Admin\AssessmentRuleController::class, 'activate'])->name('assessment-rules.activate');
    });

    // Suporte e mensagens
    Route::middleware('permission:support.manage')->group(function () {
        Route::get('/suporte', [Admin\SupportTicketController::class, 'index'])->name('tickets.index');
        Route::get('/suporte/{ticket}', [Admin\SupportTicketController::class, 'show'])->name('tickets.show');
        Route::post('/suporte/{ticket}/responder', [Admin\SupportTicketController::class, 'reply'])->name('tickets.reply');
        Route::post('/suporte/{ticket}/estado', [Admin\SupportTicketController::class, 'status'])->name('tickets.status');
        Route::get('/suporte/anexos/{reply}', [Admin\SupportTicketController::class, 'attachment'])->name('tickets.attachment');
    });
    Route::middleware('permission:contact_messages.manage')->group(function () {
        Route::get('/mensagens', [Admin\ContactMessageController::class, 'index'])->name('messages.index');
        Route::get('/mensagens/{message}', [Admin\ContactMessageController::class, 'show'])->name('messages.show');
        Route::post('/mensagens/{message}', [Admin\ContactMessageController::class, 'update'])->name('messages.update');
    });
});
