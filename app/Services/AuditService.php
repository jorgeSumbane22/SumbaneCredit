<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AuditService
{
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null,
        string $result = 'success',
        ?int $userId = null,
    ): ?AuditLog {
        try {
            $request = app()->runningInConsole() ? null : request();

            return AuditLog::create([
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'auditable_type' => $subject ? $subject->getMorphClass() : null,
                'auditable_id' => $subject?->getKey(),
                'description' => $description !== null ? mb_substr($description, 0, 255) : null,
                'old_values' => $old ? $this->sanitize($old) : null,
                'new_values' => $new ? $this->sanitize($new) : null,
                'result' => $result,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
                'url' => $request ? mb_substr($request->fullUrl(), 0, 500) : null,
                'method' => $request?->method(),
                'origin' => app()->runningInConsole() ? (app()->runningUnitTests() ? 'test' : 'console') : 'web',
            ]);
        } catch (Throwable $e) {
            // A falha da auditoria nunca deve passar despercebida.
            report($e);

            return null;
        }
    }

    public function failure(string $action, ?Model $subject, string $description): void
    {
        $this->log($action, $subject, null, null, $description, 'failure');
    }

    private function sanitize(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if (in_array($key, ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'], true)) {
                continue;
            }
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_string($value) && mb_strlen($value) > 2000) {
                $value = mb_substr($value, 0, 2000).'…';
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
