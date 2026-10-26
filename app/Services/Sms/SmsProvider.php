<?php

namespace App\Services\Sms;

interface SmsProvider
{
    public function send(string $to, string $message): void;
}
