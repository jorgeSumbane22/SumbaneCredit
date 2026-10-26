<?php

namespace App\Notifications;

class WelcomeNotification extends AppNotification
{
    public function title(): string
    {
        return 'Bem-vindo(a) ao '.config('app.name');
    }

    public function message(): string
    {
        return 'A sua conta foi criada. Complete o seu perfil e carregue os documentos de identificação para poder solicitar crédito.';
    }

    public function url(object $notifiable): ?string
    {
        return route('client.profile.edit');
    }

    public function icon(): string
    {
        return 'person-check';
    }

    public function level(): string
    {
        return 'success';
    }
}
