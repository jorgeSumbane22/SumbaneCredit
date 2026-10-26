<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const CATEGORIES = [
        'credit' => 'Pedidos de crédito', 'payments' => 'Pagamentos', 'documents' => 'Documentos',
        'account' => 'Conta e acesso', 'complaint' => 'Reclamação', 'other' => 'Outro',
    ];

    public const PRIORITIES = ['low' => 'Baixa', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'];

    public const STATUSES = [
        'open' => 'Aberto', 'in_progress' => 'Em tratamento', 'awaiting_client' => 'Aguarda resposta do cliente',
        'resolved' => 'Resolvido', 'closed' => 'Fechado',
    ];

    protected $fillable = [
        'ticket_number', 'client_id', 'user_id', 'subject', 'category', 'priority', 'status',
        'assigned_to', 'last_reply_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['last_reply_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->oldest('id');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['resolved', 'closed'], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return ['open' => 'primary', 'in_progress' => 'info', 'awaiting_client' => 'warning', 'resolved' => 'success', 'closed' => 'secondary'][$this->status] ?? 'secondary';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }
}
