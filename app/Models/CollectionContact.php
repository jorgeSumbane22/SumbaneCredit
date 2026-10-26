<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionContact extends Model
{
    public const UPDATED_AT = null;

    public const CHANNELS = [
        'call' => 'Chamada telefónica', 'sms' => 'SMS', 'email' => 'Email', 'visit' => 'Visita', 'whatsapp' => 'WhatsApp',
    ];

    public const OUTCOMES = [
        'contacted' => 'Contactado', 'no_answer' => 'Sem resposta', 'promise' => 'Promessa de pagamento',
        'refused' => 'Recusou pagar', 'wrong_number' => 'Contacto inválido', 'other' => 'Outro',
    ];

    protected $fillable = ['collection_id', 'user_id', 'channel', 'outcome', 'notes', 'contacted_at', 'next_action_at'];

    protected function casts(): array
    {
        return ['contacted_at' => 'datetime', 'next_action_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(CollectionCase::class, 'collection_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
