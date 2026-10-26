<?php

namespace App\Notifications;

use App\Models\SupportTicket;

class TicketReplyNotification extends AppNotification
{
    public function __construct(public SupportTicket $ticket, public bool $forStaff = false)
    {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        return $this->forStaff ? ['database'] : parent::via($notifiable);
    }

    public function title(): string
    {
        return $this->forStaff ? 'Nova mensagem de cliente' : 'Resposta ao seu pedido de suporte';
    }

    public function message(): string
    {
        return 'Ticket '.$this->ticket->ticket_number.': '.$this->ticket->subject;
    }

    public function url(object $notifiable): ?string
    {
        return $this->forStaff ? route('admin.tickets.show', $this->ticket) : route('client.tickets.show', $this->ticket);
    }

    public function icon(): string
    {
        return 'chat-dots';
    }
}
