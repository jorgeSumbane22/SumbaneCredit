<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/** Prestador de desenvolvimento: regista a mensagem no log. NÃO envia SMS. */
class LogSmsProvider implements SmsProvider
{
    public function send(string $to, string $message): void
    {
        Log::info('[SMS NÃO ENVIADO — driver "log"]', ['to' => $to, 'message' => $message, 'sender' => config('sumbanecredit.sms.sender_id')]);
    }
}
