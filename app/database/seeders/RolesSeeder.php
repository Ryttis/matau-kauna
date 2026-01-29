<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserRole;
use App\Models\RolePermission;
use App\Models\RolePermissionGroup;
use Illuminate\Support\Str;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Roles
        |--------------------------------------------------------------------------
        */
        $roleUser = UserRole::updateOrCreate(
            ['role' => UserRole::ROLE_USER],
            ['name' => 'User']
        );

        $roleAdmin = UserRole::updateOrCreate(
            ['role' => UserRole::ROLE_ADMIN],
            ['name' => 'Administratorius']
        );

        /*
        |--------------------------------------------------------------------------
        | 2. Permission Groups
        |--------------------------------------------------------------------------
        */
        $groups = collect([
            'Users',
            'Roles',
            'Permissions',
            'Issues',
            'Admin',
        ])->mapWithKeys(function ($name) {
            return [
                $name => RolePermissionGroup::updateOrCreate(
                    ['name' => $name],
                    []
                ),
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | 3. Permissions
        |--------------------------------------------------------------------------
        */
        $permissions = collect([
            // Users
            'users.view',
            'users.manage',

            // Roles / RBAC
            'roles.view',
            'roles.manage',
            'permissions.view',
            'permissions.manage',

            // Issues
            'issues.view',
            'issues.edit',
            'issues.assign',
            'issues.export',

            // Admin
            'admin.access',
        ])->mapWithKeys(function ($permission) {
            return [
                $permission => RolePermission::updateOrCreate(
                    ['permission' => $permission],
                    []
                ),
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | 4. Attach permissions → groups
        |--------------------------------------------------------------------------
        */
        $groups['Users']->permissions()->sync([
            $permissions['users.view']->id,
            $permissions['users.manage']->id,
        ]);

        $groups['Roles']->permissions()->sync([
            $permissions['roles.view']->id,
            $permissions['roles.manage']->id,
        ]);

        $groups['Permissions']->permissions()->sync([
            $permissions['permissions.view']->id,
            $permissions['permissions.manage']->id,
        ]);

        $groups['Issues']->permissions()->sync([
            $permissions['issues.view']->id,
            $permissions['issues.edit']->id,
            $permissions['issues.assign']->id,
            $permissions['issues.export']->id,
        ]);

        $groups['Admin']->permissions()->sync([
            $permissions['admin.access']->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5. Attach groups → roles
        |--------------------------------------------------------------------------
        */

        // ROLE_USER: minimal access
        $roleUser->permissionGroups()->sync([
            $groups['Issues']->id,
        ]);

        // ROLE_ADMIN: everything
        $roleAdmin->permissionGroups()->sync(
            $groups->pluck('id')->toArray()
        );

        /*
        |--------------------------------------------------------------------------
        | 6. Assign ROLE_ADMIN to user ID = 1
        |--------------------------------------------------------------------------
        */
        $adminUser = User::find(1);

        if ($adminUser) {
            $adminUser->roles()->syncWithoutDetaching([
                $roleAdmin->id,
            ]);
        }
    }
}
