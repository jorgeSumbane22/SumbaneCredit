<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanStatusHistory extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = ['loan_id', 'from_status', 'to_status', 'user_id', 'reason'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromLabel(): string
    {
        return $this->from_status ? (LoanStatus::tryFrom($this->from_status)?->label() ?? $this->from_status) : '—';
    }

    public function toLabel(): string
    {
        return LoanStatus::tryFrom($this->to_status)?->label() ?? $this->to_status;
    }
}
