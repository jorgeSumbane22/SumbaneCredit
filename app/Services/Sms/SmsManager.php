<?php

namespace App\Services\Sms;

use InvalidArgumentException;

/**
 * Selecciona o prestador de SMS configurado (SMS_DRIVER).
 *  - "log": NÃO envia; regista a mensagem no log (desenvolvimento).
 *  - "null": desactivado.
 * Um prestador real (operadora/agregador) exige contrato e credenciais: implementar
 * SmsProvider e registá-lo aqui.
 */
class SmsManager
{
    public function driver(?string $name = null): SmsProvider
    {
        $name ??= config('sumbanecredit.sms.driver', 'null');

        return match ($name) {
            'log' => new LogSmsProvider,
            'null' => new NullSmsProvider,
            default => throw new InvalidArgumentException("Prestador de SMS não suportado: {$name}"),
        };
    }
}
