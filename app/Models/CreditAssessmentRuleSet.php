<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditAssessmentRuleSet extends Model
{
    protected $fillable = ['version', 'name', 'rules', 'is_active', 'notes', 'created_by', 'validated_by', 'validated_at'];

    protected function casts(): array
    {
        return ['rules' => 'array', 'is_active' => 'boolean', 'validated_at' => 'datetime'];
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->orderByDesc('version')->first();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
