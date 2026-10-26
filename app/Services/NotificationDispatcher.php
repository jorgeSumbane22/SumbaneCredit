<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Spatie\Permission\Models\Permission;

class NotificationDispatcher
{
    /** Envia a notificação apenas uma vez para a chave indicada (evita duplicados). */
    public function once(string $key, User $user, Notification $notification): bool
    {
        try {
            DB::table('notification_logs')->insert([
                'dedup_key' => mb_substr($key, 0, 191),
                'user_id' => $user->id,
                'notification' => class_basename($notification),
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                return false;
            }
            throw $e;
        }

        $user->notify($notification);

        return true;
    }

    /** Notifica todos os funcionários activos com a permissão indicada. */
    public function staffWithPermission(string $permission, Notification $notification, ?int $exceptUserId = null): void
    {
        if (! Permission::where('name', $permission)->exists()) {
            return;
        }
        $users = User::permission($permission)->where('status', 'active')->where('is_staff', true)
            ->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->get();
        if ($users->isNotEmpty()) {
            NotificationFacade::send($users, $notification);
        }
    }
}
