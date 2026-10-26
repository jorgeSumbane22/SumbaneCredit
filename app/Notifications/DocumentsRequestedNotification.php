<?php

namespace App\Notifications;

use App\Models\LoanApplication;

class DocumentsRequestedNotification extends AppNotification
{
    public function __construct(public LoanApplication $application)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Informação ou documentos em falta';
    }

    public function message(): string
    {
        return "O pedido {$this->application->application_number} necessita de informação adicional: ".$this->application->info_request;
    }

    public function url(object $notifiable): ?string
    {
        return route('client.applications.show', $this->application);
    }

    public function icon(): string
    {
        return 'exclamation-circle';
    }

    public function level(): string
    {
        return 'warning';
    }

    public function smsText(): ?string
    {
        return config('app.name').": o pedido {$this->application->application_number} precisa de documentos adicionais. Consulte o portal.";
    }
}
