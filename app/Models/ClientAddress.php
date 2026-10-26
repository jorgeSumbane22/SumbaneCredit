<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAddress extends Model
{
    protected $fillable = [
        'client_id', 'type', 'province', 'district', 'locality', 'neighborhood', 'address_line', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function oneLine(): string
    {
        return collect([$this->address_line, $this->neighborhood, $this->locality, $this->district, $this->province])
            ->filter()->implode(', ');
    }
}
