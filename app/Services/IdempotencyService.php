<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Protecção contra submissões repetidas (duplo clique, reenvio do formulário).
 * Cada formulário financeiro inclui uma chave única; a mesma chave só é processada uma vez.
 */
class IdempotencyService
{
    /** Regista a chave; devolve false se já tiver sido utilizada. Deve correr dentro da transacção. */
    public function claim(?string $key, string $operation): bool
    {
        if ($key === null || $key === '') {
            return true;
        }

        try {
            DB::table('idempotency_keys')->insert([
                'key' => mb_substr($key, 0, 100),
                'user_id' => Auth::id(),
                'operation' => $operation,
                'created_at' => now(),
            ]);

            return true;
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || ($e->errorInfo[1] ?? null) === 1062) {
                return false;
            }
            throw $e;
        }
    }

    public function attach(?string $key, string $type, int $id): void
    {
        if ($key) {
            DB::table('idempotency_keys')->where('key', $key)->update(['resource_type' => $type, 'resource_id' => $id]);
        }
    }

    public function resource(string $key): ?object
    {
        return DB::table('idempotency_keys')->where('key', $key)->first();
    }
}
