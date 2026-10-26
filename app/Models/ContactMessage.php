<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    public const STATUSES = ['new' => 'Nova', 'read' => 'Lida', 'answered' => 'Respondida', 'archived' => 'Arquivada'];

    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'status', 'internal_notes', 'handled_by', 'handled_at', 'ip_address'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
