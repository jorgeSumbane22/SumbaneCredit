<?php

namespace App\Notifications;

use App\Models\LoanApplication;

/** Para funcionários: o cliente respondeu ao pedido de informação. */
class ApplicationInfoProvidedNotification extends AppNotification
{
    public function __construct(public LoanApplication $application)
    {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function title(): string
    {
        return 'Cliente enviou informação adicional';
    }

    public function message(): string
    {
        return "O cliente respondeu ao pedido de informação do pedido {$this->application->application_number}.";
    }

    public function url(object $notifiable): ?string
    {
        return route('admin.applications.show', $this->application);
    }

    public function icon(): string
    {
        return 'reply';
    }
}
