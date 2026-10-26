<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** Tags HTML permitidas nos conteúdos do website (o restante é removido). */
    private const ALLOWED_HTML = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a>';

    public function __construct(private readonly SettingsService $settings) {}

    /** Grupos editáveis consoante a permissão. */
    private function editableGroups(Request $request): array
    {
        $groups = [];
        if ($request->user()->can('content.manage')) {
            $groups['institution'] = 'Instituição';
            $groups['content'] = 'Conteúdos do website';
        }
        if ($request->user()->can('settings.manage')) {
            $groups['institution'] = 'Instituição';
            $groups['finance'] = 'Regras operacionais';
            $groups['uploads'] = 'Ficheiros';
            $groups['security'] = 'Segurança';
            $groups['notifications'] = 'Notificações';
        }

        return $groups;
    }

    public function index(Request $request): View
    {
        $groups = $this->editableGroups($request);
        $definitions = collect(SettingsService::DEFINITIONS)->filter(fn ($d) => isset($groups[$d[0]]))->groupBy(fn ($d) => $d[0], true);

        return view('admin.settings.index', [
            'groups' => $groups,
            'definitions' => $definitions,
            'values' => collect(SettingsService::DEFINITIONS)->keys()->mapWithKeys(fn ($k) => [$k => $this->settings->get($k)]),
            'integrations' => [
                'Email' => config('mail.default') === 'log' ? 'Modo "log": os emails são escritos em storage/logs (não são enviados).' : 'Servidor: '.config('mail.mailers.'.config('mail.default').'.host'),
                'SMS' => match (config('sumbanecredit.sms.driver')) { 'log' => 'Modo "log": as mensagens são registadas no log, não enviadas.', 'null' => 'Desactivado.', default => config('sumbanecredit.sms.driver') },
                'M-Pesa' => config('sumbanecredit.payment_gateways.mpesa.enabled') ? 'Activado (requer credenciais válidas)' : 'Não configurado — registo manual.',
                'e-Mola' => config('sumbanecredit.payment_gateways.emola.enabled') ? 'Activado (requer credenciais válidas)' : 'Não configurado — registo manual.',
                'Filas' => 'QUEUE_CONNECTION='.config('queue.default'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $groups = $this->editableGroups($request);
        $input = (array) $request->input('settings', []);
        $rules = [];
        foreach (SettingsService::DEFINITIONS as $key => [$group, $type]) {
            if (! isset($groups[$group])) {
                continue;
            }
            $field = 'settings.'.str_replace('.', '__', $key);
            $rules[$field] = match ($type) {
                'number' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'boolean' => ['nullable', 'boolean'],
                'html', 'text' => ['nullable', 'string', 'max:60000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }
        $rules['settings.finance__allocation_order'] = ['nullable', 'string', 'regex:/^(penalty|fee|interest|principal)(,(penalty|fee|interest|principal)){3}$/'];
        $request->validate($rules, ['settings.finance__allocation_order.regex' => 'A ordem de imputação deve conter penalty, fee, interest e principal separados por vírgulas.']);

        foreach (SettingsService::DEFINITIONS as $key => [$group, $type]) {
            if (! isset($groups[$group])) {
                continue;
            }
            $value = $input[str_replace('.', '__', $key)] ?? null;
            if ($type === 'boolean') {
                $value = ! empty($value) ? '1' : '0';
            } elseif ($type === 'html' && $value !== null) {
                $value = strip_tags($value, self::ALLOWED_HTML);
                $value = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $value);
                $value = preg_replace('/href\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\1/i', 'href="#"', $value);
            }
            if ($key === 'finance.allocation_order' && $value) {
                $parts = explode(',', $value);
                if (count(array_unique($parts)) !== 4) {
                    return back()->withInput()->with('error', 'A ordem de imputação deve incluir cada componente uma única vez.');
                }
            }
            $this->settings->set($key, $value);
        }

        return back()->with('success', 'Definições guardadas.');
    }
}
