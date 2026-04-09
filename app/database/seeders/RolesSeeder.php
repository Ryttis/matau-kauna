<?php

namespace Database\Seeders;

use App\Models\UserRolePermission;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('user_role_permission_map')->truncate();
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
            'issues.publish',
            'classification.edit',
            'statistics.view',
            'admin.access',
            'archive.manage',
        ])->mapWithKeys(function ($permission) {
            return [
                $permission => UserRolePermission::updateOrCreate(
                    ['permission' => $permission],
                    []
                ),
            ];
        });

        $roleSystemAdmin->permissions()->sync([
            $permissions['users.view']->id,
            $permissions['users.manage']->id,
            $permissions['roles.view']->id,
            $permissions['roles.manage']->id,
            $permissions['permissions.view']->id,
            $permissions['permissions.manage']->id,
            $permissions['issues.view']->id,
            $permissions['issues.edit']->id,
            $permissions['issues.assign']->id,
            $permissions['issues.export']->id,
            $permissions['issues.publish']->id,
            $permissions['classification.edit']->id,
            $permissions['statistics.view']->id,
            $permissions['admin.access']->id,
            $permissions['archive.manage']->id,
        ]);

        $roleProblemAdmin->permissions()->sync([
            $permissions['issues.view']->id,
            $permissions['issues.edit']->id,
            $permissions['issues.assign']->id,
            $permissions['issues.export']->id,
            $permissions['issues.publish']->id,
            $permissions['classification.edit']->id,
            $permissions['statistics.view']->id,
        ]);

        $roleInstitutionAdmin->permissions()->sync([
            $permissions['issues.view']->id,
            $permissions['issues.edit']->id,
            $permissions['issues.assign']->id,
            $permissions['issues.export']->id,
            $permissions['statistics.view']->id,
        ]);

        $roleMunicipalityRep->permissions()->sync([
            $permissions['issues.view']->id,
            $permissions['issues.edit']->id,
            $permissions['issues.assign']->id,
            $permissions['issues.export']->id,
            $permissions['statistics.view']->id,
        ]);

        $roleEmployee->permissions()->sync([
            $permissions['issues.view']->id,
            $permissions['issues.edit']->id,
            $permissions['issues.assign']->id,
            $permissions['issues.export']->id,
            $permissions['statistics.view']->id,
        ]);

        $roleReporter->permissions()->sync([]);

        $adminUser = User::find(1);
        if ($adminUser) {
            $adminUser->roles()->sync([$roleSystemAdmin->id]);
        }
    }
}
