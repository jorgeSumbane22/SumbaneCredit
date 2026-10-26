<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use Auditable, HasFactory, HasRoles, Notifiable;

    public const STAFF_ROLES = [
        'super-admin' => 'Super Administrador',
        'admin' => 'Administrador',
        'gestor-credito' => 'Gestor de Crédito',
        'agente-credito' => 'Agente de Crédito',
        'caixa' => 'Caixa',
        'contabilista' => 'Contabilista',
    ];

    public const CLIENT_ROLE = 'cliente';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'status', 'is_staff', 'created_by',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'password' => 'hashed',
            'is_staff' => 'boolean',
        ];
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isStaff(): bool
    {
        return (bool) $this->is_staff;
    }

    public function isClient(): bool
    {
        return ! $this->is_staff && $this->hasRole(self::CLIENT_ROLE);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function roleLabel(): string
    {
        $role = $this->getRoleNames()->first();

        return self::STAFF_ROLES[$role] ?? ($role === self::CLIENT_ROLE ? 'Cliente' : (string) $role);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(end($parts) ?: '', 0, 1));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
