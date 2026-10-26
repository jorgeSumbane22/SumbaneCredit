<?php

namespace App\Notifications;

use App\Models\LoanApplication;
use App\Support\Money;

class ApplicationSubmittedNotification extends AppNotification
{
    public function __construct(public LoanApplication $application)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return 'Pedido de crédito submetido';
    }

    public function message(): string
    {
        return "O pedido {$this->application->application_number} de ".Money::format($this->application->requested_amount).' foi recebido e será analisado pela nossa equipa.';
    }

    public function url(object $notifiable): ?string
    {
        return route('client.applications.show', $this->application);
    }

    public function icon(): string
    {
        return 'file-earmark-text';
    }

    public function smsText(): ?string
    {
        return config('app.name').": pedido {$this->application->application_number} recebido. Acompanhe no portal.";
    }
}
