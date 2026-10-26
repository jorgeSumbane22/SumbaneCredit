<?php

namespace App\Models\Concerns;

use App\Services\AuditService;

/**
 * Regista automaticamente na auditoria a criação e a alteração do modelo.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(AuditService::class)->log(
                strtolower(class_basename($model)).'.created',
                $model,
                null,
                $model->auditSafe($model->getAttributes()),
            );
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            app(AuditService::class)->log(
                strtolower(class_basename($model)).'.updated',
                $model,
                $model->auditSafe($old),
                $model->auditSafe($changes),
            );
        });
    }

    public function auditSafe(array $values): array
    {
        $hidden = array_merge(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'], $this->auditExclude ?? []);

        return array_diff_key($values, array_flip($hidden));
    }
}
