<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationReview extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'submitted' => 'Pedido submetido',
        'note' => 'Observação',
        'review_started' => 'Análise iniciada',
        'document_verified' => 'Documento verificado',
        'document_rejected' => 'Documento rejeitado',
        'info_requested' => 'Pedido de informação/documentos',
        'info_provided' => 'Informação/documentos entregues pelo cliente',
        'assessment' => 'Avaliação de crédito',
        'forwarded' => 'Encaminhado para decisão',
        'assigned' => 'Atribuído a analista',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
        'cancelled' => 'Cancelado',
    ];

    protected $fillable = ['loan_application_id', 'user_id', 'action', 'from_status', 'to_status', 'notes', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /** Acções visíveis ao cliente no acompanhamento do pedido. */
    public function isClientVisible(): bool
    {
        return in_array($this->action, ['submitted', 'review_started', 'info_requested', 'info_provided', 'forwarded', 'approved', 'rejected', 'cancelled'], true);
    }
}
