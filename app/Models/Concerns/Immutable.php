<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Registos imutáveis (razão financeiro, auditoria): não podem ser alterados nem apagados.
 * Correcções fazem-se sempre com movimentos compensatórios.
 */
trait Immutable
{
    /** Colunas que podem ser alteradas depois da criação (ex.: marcação de estorno). */
    protected function mutableColumns(): array
    {
        return [];
    }

    public static function bootImmutable(): void
    {
        static::updating(function ($model) {
            $changed = array_keys($model->getDirty());
            if (array_diff($changed, $model->mutableColumns()) !== []) {
                throw new LogicException(class_basename($model).' é imutável e não pode ser alterado.');
            }
        });

        static::deleting(function ($model) {
            throw new LogicException(class_basename($model).' é imutável e não pode ser apagado.');
        });
    }
}
