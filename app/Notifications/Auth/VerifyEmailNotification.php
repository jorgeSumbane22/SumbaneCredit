<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirme o seu endereço de email — '.config('app.name'))
            ->greeting('Olá!')
            ->line('Obrigado pelo seu registo. Clique no botão abaixo para confirmar o seu endereço de email.')
            ->action('Confirmar email', $url)
            ->line('Se não criou uma conta, ignore esta mensagem.')
            ->salutation('Com os melhores cumprimentos, '.config('app.name'));
    }
}
