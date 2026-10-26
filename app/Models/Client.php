<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use Auditable, HasFactory;

    public const DOCUMENT_TYPES = [
        'bi' => 'Bilhete de Identidade',
        'passaporte' => 'Passaporte',
        'dire' => 'DIRE',
        'outro' => 'Outro',
    ];

    public const STATUSES = [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'blocked' => 'Bloqueado',
    ];

    public const KYC_STATUSES = [
        'pending' => 'Identificação por verificar',
        'verified' => 'Identificação verificada',
        'rejected' => 'Identificação rejeitada',
    ];

    public const PROVINCES = [
        'Maputo Cidade', 'Maputo Província', 'Gaza', 'Inhambane', 'Sofala', 'Manica',
        'Tete', 'Zambézia', 'Nampula', 'Cabo Delgado', 'Niassa',
    ];

    public const EMPLOYMENT_TYPES = [
        'employed' => 'Trabalhador por conta de outrem',
        'self_employed' => 'Trabalhador por conta própria',
        'informal' => 'Actividade informal',
        'retired' => 'Reformado/Pensionista',
        'other' => 'Outro',
    ];

    protected $fillable = [
        'user_id', 'client_number', 'full_name', 'document_type', 'document_number', 'document_expiry',
        'nuit', 'birth_date', 'nationality', 'phone', 'phone_alt', 'email', 'occupation', 'monthly_income',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
        'status', 'kyc_status', 'kyc_verified_by', 'kyc_verified_at', 'notes', 'is_demo', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'document_expiry' => 'date',
            'kyc_verified_at' => 'datetime',
            'monthly_income' => 'decimal:2',
            'is_demo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): HasOne
    {
        return $this->hasOne(ClientAddress::class)->where('is_primary', true);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function employment(): HasOne
    {
        return $this->hasOne(ClientEmploymentDetail::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class)->latest();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class)->latest();
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_at');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class)->latest();
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class)->where('status', LoanStatus::Active);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('full_name', 'like', "%{$term}%")
                ->orWhere('client_number', 'like', "%{$term}%")
                ->orWhere('document_number', 'like', "%{$term}%")
                ->orWhere('nuit', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function documentTypeLabel(): string
    {
        return self::DOCUMENT_TYPES[$this->document_type] ?? $this->document_type;
    }
}
