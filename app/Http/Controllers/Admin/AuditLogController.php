<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Consulta da auditoria (apenas leitura — os registos são imutáveis). */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::with('user')
            ->search($request->query('q'))
            ->when($request->query('utilizador'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('resultado'), fn ($q, $r) => $q->where('result', $r))
            ->when($request->query('modulo'), fn ($q, $m) => $q->where('action', 'like', $m.'.%'))
            ->when($request->query('de'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('ate'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')->paginate(40)->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'users' => User::where('is_staff', true)->orderBy('name')->pluck('name', 'id'),
            'modules' => [
                'auth' => 'Autenticação', 'client' => 'Clientes', 'document' => 'Documentos', 'application' => 'Pedidos',
                'loan' => 'Empréstimos', 'disbursement' => 'Desembolsos', 'payment' => 'Pagamentos', 'penalty' => 'Penalizações',
                'collection' => 'Cobranças', 'reconciliation' => 'Reconciliação', 'product' => 'Produtos', 'staff' => 'Funcionários',
                'role' => 'Perfis', 'settings' => 'Definições', 'report' => 'Relatórios',
            ],
        ]);
    }

    public function show(AuditLog $log): View
    {
        return view('admin.audit.show', ['log' => $log->load('user')]);
    }
}
