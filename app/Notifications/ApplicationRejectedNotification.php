<?php

namespace App\Notifications;

use App\Models\LoanApplication;

class ApplicationRejectedNotification extends AppNotification
{
    public function __construct(public LoanApplication $application)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Pedido de crédito não aprovado';
    }

    public function message(): string
    {
        return 'Lamentamos, o pedido '.$this->application->application_number.' não foi aprovado. Motivo: '.$this->application->rejection_reason;
    }

    public function url(object $notifiable): ?string
    {
        return route('client.applications.show', $this->application);
    }

    public function icon(): string
    {
        return 'x-circle';
    }

    public function level(): string
    {
        return 'danger';
    }
}
