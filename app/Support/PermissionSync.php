<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Cria/actualiza permissões e perfis a partir do catálogo (idempotente). */
final class PermissionSync
{
    public static function run(bool $resetRolePermissions = false): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(Permissions::ALL) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (Permissions::ROLES as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            if ($resetRolePermissions || $role->permissions()->doesntExist()) {
                $role->syncPermissions($permissions);
            }
        }

        // Garantia de segregação, mesmo que a matriz seja alterada manualmente na base de dados.
        Role::findByName('super-admin', 'web')->revokePermissionTo(
            array_values(array_filter(Permissions::SUPER_ADMIN_FORBIDDEN, fn ($p) => Role::findByName('super-admin', 'web')->hasPermissionTo($p)))
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
