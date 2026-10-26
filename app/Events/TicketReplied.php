<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketReplied implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public \App\Models\SupportTicket $ticket, public \App\Models\TicketReply $reply) {}
}
