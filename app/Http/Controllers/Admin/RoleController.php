<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(): View
    {
        $roles = Role::with('permissions')->whereIn('name', array_keys(User::STAFF_ROLES))->get()
            ->sortBy(fn ($r) => array_search($r->name, array_keys(User::STAFF_ROLES)));

        return view('admin.roles.index', [
            'roles' => $roles,
            'permissions' => Permissions::ALL,
            'forbidden' => Permissions::SUPER_ADMIN_FORBIDDEN,
            'counts' => collect(array_keys(User::STAFF_ROLES))->mapWithKeys(fn ($r) => [$r => User::role($r)->count()]),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless(array_key_exists($role->name, User::STAFF_ROLES), 404);
        $data = $request->validate(['permissions' => ['array'], 'permissions.*' => ['string', 'in:'.implode(',', array_keys(Permissions::ALL))]]);
        $selected = $data['permissions'] ?? [];

        // Segregação de funções: o Super Administrador não pode receber permissões operacionais financeiras.
        if ($role->name === 'super-admin') {
            $blocked = array_intersect($selected, Permissions::SUPER_ADMIN_FORBIDDEN);
            if ($blocked) {
                return back()->with('error', 'O perfil Super Administrador não pode receber: '.implode(', ', $blocked).'.');
            }
            foreach (['roles.manage', 'dashboard.admin'] as $required) {
                if (! in_array($required, $selected, true)) {
                    return back()->with('error', 'O Super Administrador tem de manter a permissão '.$required.'.');
                }
            }
        }
        if (! in_array('dashboard.admin', $selected, true)) {
            $selected[] = 'dashboard.admin';
        }

        $old = $role->permissions->pluck('name')->sort()->values()->all();
        $role->syncPermissions(Permission::whereIn('name', $selected)->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->audit->log('role.permissions_updated', $role, ['permissions' => $old], ['permissions' => collect($selected)->sort()->values()->all()]);

        return back()->with('success', 'Permissões do perfil '.User::STAFF_ROLES[$role->name].' actualizadas.');
    }
}
