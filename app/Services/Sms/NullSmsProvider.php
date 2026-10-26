<?php

namespace App\Services\Sms;

class NullSmsProvider implements SmsProvider
{
    public function send(string $to, string $message): void
    {
        // SMS desactivado.
    }
}
