<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;

class SupportTicketPolicy
{
    use ChecksOwnership;

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $this->staffCan($user, 'support.manage') || $this->owns($user, $ticket->client_id);
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $this->view($user, $ticket) && $ticket->status !== 'closed';
    }
}
