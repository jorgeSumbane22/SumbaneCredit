<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClientDocument extends Model
{
    public const TYPES = [
        'bi' => 'Documento de identificação',
        'nuit' => 'Cartão/Declaração de NUIT',
        'proof_of_address' => 'Comprovativo de residência',
        'proof_of_income' => 'Comprovativo de rendimento',
        'employer_declaration' => 'Declaração da entidade empregadora',
        'bank_statement' => 'Extracto bancário',
        'business_license' => 'Alvará/Licença de actividade',
        'collateral_document' => 'Documento de garantia',
        'other' => 'Outro',
    ];

    public const STATUSES = [
        'pending' => 'Por verificar',
        'verified' => 'Verificado',
        'rejected' => 'Rejeitado',
    ];

    protected $fillable = [
        'client_id', 'type', 'original_name', 'disk', 'path', 'mime_type', 'size', 'checksum',
        'status', 'notes', 'rejection_reason', 'uploaded_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'size' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(LoanApplication::class, 'loan_application_documents');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return ['pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'][$this->status] ?? 'secondary';
    }

    public function humanSize(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1, ',', '.').' MB'
            : number_format($this->size / 1024, 0, ',', '.').' KB';
    }
}
