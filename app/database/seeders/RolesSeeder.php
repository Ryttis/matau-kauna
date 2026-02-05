<?php

namespace Database\Seeders;

use App\Models\UserRolePermission;
use App\Models\UserRolePermissionGroup;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('user_role_permission_group_role_map')->truncate();
        DB::table('user_role_permission_group_map')->truncate();
        DB::table('user_role_permission_group')->truncate();
        DB::table('user_role_permission')->truncate();

        $roleSystemAdmin = UserRole::updateOrCreate(
            ['role' => UserRole::ROLE_SYSTEM_ADMIN],
            ['name' => 'Sistemos administratorius']
        );

        $roleProblemAdmin = UserRole::updateOrCreate(
            ['role' => UserRole::ROLE_PROBLEM_ADMIN],
            ['name' => 'Problemų administratorius']
        );

        $roleInstitutionAdmin = UserRole::updateOrCreate(
            ['role' => UserRole::ROLE_INSTITUTION_ADMIN],
            ['name' => 'Įstaigos administratorius']
        );

        $roleMunicipalityRep = UserRole::updateOrCreate(
            ['role' => UserRole::ROLE_MUNICIPALITY_REP],
            ['name' => 'Savivaldybės atstovas']
        );

        $roleEmployee = UserRole::updateOrCreate(
            ['role' => UserRole::ROLE_EMPLOYEE],
            ['name' => 'Įstaigos darbuotojas']
        );

        $roleReporter = UserRole::updateOrCreate(
            ['role' => UserRole::ROLE_REPORTER],
            ['name' => 'Pareiškėjas']
        );

        $groups = collect([
            'Users',
            'Roles',
            'Permissions',
            'Issues',
            'Statistics',
            'Admin',
        ])->mapWithKeys(function ($name) {
            return [
                $name => UserRolePermissionGroup::updateOrCreate(
                    ['name' => $name],
                    []
                ),
            ];
        });

        $permissions = collect([
            'users.view',
            'users.manage',
            'roles.view',
            'roles.manage',
            'permissions.view',
            'permissions.manage',
            'issues.view',
            'issues.edit',
            'issues.assign',
            'issues.export',
            'statistics.view',
            'admin.access',
        ])->mapWithKeys(function ($permission) {
            return [
                $permission => UserRolePermission::updateOrCreate(
                    ['permission' => $permission],
                    []
                ),
            ];
        });

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

        $groups['Statistics']->permissions()->sync([
            $permissions['statistics.view']->id,
        ]);

        $groups['Admin']->permissions()->sync([
            $permissions['admin.access']->id,
        ]);

        $roleSystemAdmin->permissionGroups()->sync($groups->pluck('id')->toArray());

        $roleProblemAdmin->permissionGroups()->sync([
            $groups['Issues']->id,
            $groups['Statistics']->id,
        ]);

        $roleInstitutionAdmin->permissionGroups()->sync([
            $groups['Issues']->id,
            $groups['Statistics']->id,
        ]);

        $roleMunicipalityRep->permissionGroups()->sync([
            $groups['Issues']->id,
            $groups['Statistics']->id,
        ]);

        $roleEmployee->permissionGroups()->sync([
            $groups['Issues']->id,
            $groups['Statistics']->id,
        ]);

        $roleReporter->permissionGroups()->sync([]);

        $adminUser = User::find(1);
        if ($adminUser) {
            $adminUser->roles()->sync([$roleSystemAdmin->id]);
        }
    }
}
