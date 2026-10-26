<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Redefinição da palavra-passe — '.config('app.name'))
            ->greeting('Olá!')
            ->line('Recebemos um pedido para redefinir a palavra-passe da sua conta.')
            ->action('Redefinir palavra-passe', $url)
            ->line('Esta ligação expira em '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutos.')
            ->line('Se não pediu a redefinição, ignore esta mensagem; a sua palavra-passe mantém-se.')
            ->salutation('Com os melhores cumprimentos, '.config('app.name'));
    }
}
