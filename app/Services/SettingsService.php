<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SettingsService
{
    private const CACHE_KEY = 'system_settings.all';

    /** Definições conhecidas: chave => [grupo, tipo, rótulo, valor por omissão]. */
    public const DEFINITIONS = [
        // Instituição (dados reais devem ser preenchidos pela instituição — nada é inventado)
        'institution.name' => ['institution', 'string', 'Nome comercial', 'SumbaneCredit'],
        'institution.tagline' => ['institution', 'string', 'Slogan', 'Crédito simples, gestão inteligente.'],
        'institution.legal_name' => ['institution', 'string', 'Denominação legal (a preencher pela instituição)', ''],
        'institution.registration_info' => ['institution', 'text', 'Autorização/registo junto das entidades competentes (a preencher pela instituição)', ''],
        'institution.nuit' => ['institution', 'string', 'NUIT da instituição', ''],
        'institution.address' => ['institution', 'string', 'Endereço', ''],
        'institution.phone' => ['institution', 'string', 'Telefone', ''],
        'institution.email' => ['institution', 'string', 'Email de contacto', ''],
        'institution.whatsapp' => ['institution', 'string', 'WhatsApp', ''],
        'institution.hours' => ['institution', 'string', 'Horário de atendimento', 'Segunda a sexta, 08:00–16:30'],

        // Conteúdos do website
        'content.hero_title' => ['content', 'string', 'Título principal (página inicial)', 'Crédito simples, gestão inteligente.'],
        'content.hero_subtitle' => ['content', 'text', 'Subtítulo (página inicial)', 'Solicite o seu microcrédito online, acompanhe o pedido em tempo real e faça a gestão das suas prestações num só lugar.'],
        'content.about' => ['content', 'html', 'Sobre nós', ''],
        'content.mission' => ['content', 'text', 'Missão', ''],
        'content.vision' => ['content', 'text', 'Visão', ''],
        'content.values' => ['content', 'text', 'Valores (um por linha)', ''],
        'content.how_it_works' => ['content', 'html', 'Como funciona', ''],
        'content.privacy_policy' => ['content', 'html', 'Política de privacidade', ''],
        'content.terms' => ['content', 'html', 'Termos e condições', ''],

        // Regras operacionais
        'finance.allocation_order' => ['finance', 'string', 'Ordem de imputação dos pagamentos', 'penalty,fee,interest,principal'],
        'finance.due_reminder_days' => ['finance', 'number', 'Dias de antecedência do aviso de vencimento', '3'],
        'finance.require_kyc_for_disbursement' => ['finance', 'boolean', 'Exigir identificação verificada antes do desembolso', '1'],
        'finance.require_distinct_disbursement_authorizer' => ['finance', 'boolean', 'Autorização do desembolso por utilizador diferente do requerente', '1'],
        'finance.collection_auto_assign' => ['finance', 'boolean', 'Atribuir cobranças ao agente do empréstimo', '1'],
        'uploads.max_kb' => ['uploads', 'number', 'Tamanho máximo de cada ficheiro (KB)', '5120'],

        // Segurança e notificações
        'security.require_2fa_staff' => ['security', 'boolean', 'Exigir autenticação de dois factores aos funcionários', '0'],
        'notifications.sms_enabled' => ['notifications', 'boolean', 'Enviar SMS (requer prestador configurado)', '0'],
    ];

    public function all(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('system_settings')) {
                    return [];
                }

                return SystemSetting::pluck('value', 'key')->all();
            });
        } catch (Throwable) {
            return [];
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }

        return $default ?? (self::DEFINITIONS[$key][3] ?? null);
    }

    public function bool(string $key): bool
    {
        return in_array((string) $this->get($key), ['1', 'true', 'on', 'yes'], true);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        $def = self::DEFINITIONS[$key] ?? ['general', 'string', $key, null];
        $setting = SystemSetting::firstOrNew(['key' => $key]);
        $old = $setting->value;
        $setting->fill([
            'group' => $def[0],
            'type' => $def[1],
            'label' => $def[2],
            'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
            'updated_by' => Auth::id(),
        ])->save();

        if ($old !== $setting->value) {
            app(AuditService::class)->log('settings.updated', $setting, ['value' => $old], ['value' => $setting->value], "Definição {$key} alterada");
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function allocationOrder(): array
    {
        return array_map('trim', explode(',', (string) $this->get('finance.allocation_order')));
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
