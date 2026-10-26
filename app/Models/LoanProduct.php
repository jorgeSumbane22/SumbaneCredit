<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanProduct extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code', 'slug', 'name', 'description', 'requirements', 'contract_conditions',
        'status', 'is_public', 'current_version_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LoanProductVersion::class)->orderByDesc('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(LoanProductVersion::class, 'current_version_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereNotNull('current_version_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->active()->where('is_public', true);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
