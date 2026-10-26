<?php

namespace App\Services;

use App\Events\TicketReplied;
use App\Exceptions\BusinessRuleException;
use App\Models\Client;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SupportService
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly DocumentStorageService $storage,
        private readonly AuditService $audit,
    ) {}

    public function open(Client $client, User $user, array $data, ?UploadedFile $attachment): SupportTicket
    {
        return DB::transaction(function () use ($client, $user, $data, $attachment) {
            $ticket = SupportTicket::create([
                'ticket_number' => $this->numbers->next('ticket'),
                'client_id' => $client->id,
                'user_id' => $user->id,
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'open',
                'last_reply_at' => now(),
            ]);
            $this->addReply($ticket, $user, $data['message'], $attachment, false, notify: false);
            $this->audit->log('ticket.opened', $ticket);

            return $ticket;
        });
    }

    public function reply(SupportTicket $ticket, User $user, string $message, ?UploadedFile $attachment, ?string $newStatus = null): TicketReply
    {
        if ($ticket->status === 'closed') {
            throw new BusinessRuleException('O ticket está fechado.');
        }

        return DB::transaction(function () use ($ticket, $user, $message, $attachment, $newStatus) {
            $isStaff = $user->isStaff();
            $reply = $this->addReply($ticket, $user, $message, $attachment, $isStaff);

            $status = $newStatus ?? ($isStaff ? 'awaiting_client' : 'open');
            $ticket->update([
                'status' => $status,
                'last_reply_at' => now(),
                'assigned_to' => $ticket->assigned_to ?? ($isStaff ? $user->id : null),
                'closed_at' => in_array($status, ['resolved', 'closed'], true) ? now() : null,
            ]);

            return $reply;
        });
    }

    private function addReply(SupportTicket $ticket, User $user, string $message, ?UploadedFile $attachment, bool $isStaff, bool $notify = true): TicketReply
    {
        $stored = $attachment ? $this->storage->store($attachment, "tickets/{$ticket->id}") : null;
        $reply = $ticket->replies()->create([
            'user_id' => $user->id,
            'message' => $message,
            'attachment_path' => $stored['path'] ?? null,
            'attachment_name' => $stored['original_name'] ?? null,
            'is_staff' => $isStaff,
        ]);
        if ($notify) {
            TicketReplied::dispatch($ticket, $reply);
        }

        return $reply;
    }

    public function updateStatus(SupportTicket $ticket, User $user, string $status, ?int $assignee): void
    {
        $old = $ticket->only(['status', 'assigned_to']);
        $ticket->update([
            'status' => $status,
            'assigned_to' => $assignee,
            'closed_at' => in_array($status, ['resolved', 'closed'], true) ? ($ticket->closed_at ?? now()) : null,
        ]);
        $this->audit->log('ticket.updated', $ticket, $old, ['status' => $status, 'assigned_to' => $assignee]);
    }
}
