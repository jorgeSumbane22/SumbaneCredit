<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->query('filtro');
        $notifications = $user->notifications()
            ->when($filter === 'nao-lidas', fn ($q) => $q->whereNull('read_at'))
            ->latest()->paginate(20)->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'layout' => $user->isStaff() ? 'layouts.admin' : 'layouts.client',
        ]);
    }

    /** Abre uma notificação do próprio utilizador e marca-a como lida. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        // Apenas URLs da própria aplicação (evita redireccionamentos abertos).
        if ($url && str_starts_with($url, config('app.url'))) {
            return redirect()->to($url);
        }
        if ($url && str_starts_with($url, url('/'))) {
            return redirect()->to($url);
        }

        return redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Todas as notificações foram marcadas como lidas.');
    }
}
