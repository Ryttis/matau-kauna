<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
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

        $permissions = collect(PermissionSeeder::PERMISSIONS)
            ->keys()
            ->mapWithKeys(fn (string $permission) => [
                $permission => \App\Models\UserRolePermission::where('permission', $permission)->first(),
            ]);

        $allPermissionIds = $permissions
            ->filter()
            ->pluck('id')
            ->values()
            ->all();

        $problemAdminPermissionKeys = [
            'issues.view',
            'issues.edit',
            'issues.assign',
            'issues.reassign',
            'issues.delete',
            'issues.merge',
            'issues.export',
            'issues.publish',
            'automation.manage',
            'classification.edit',
            'statistics.view',
        ];

        $institutionScopedPermissionKeys = [
            'issues.view',
            'issues.edit',
            'issues.assign',
            'issues.export',
            'issues.merge',
            'statistics.view',
        ];

        $institutionAdminPermissionKeys = array_values(array_unique(array_merge(
            $institutionScopedPermissionKeys,
            [
                'invitations.manage',
                'teams.manage',
            ]
        )));

        $roleSystemAdmin->permissions()->sync($allPermissionIds);

        $roleProblemAdmin->permissions()->sync(
            collect($problemAdminPermissionKeys)
                ->map(fn ($k) => $permissions[$k]?->id)
                ->filter()
                ->values()
                ->all()
        );

        $roleInstitutionAdmin->permissions()->sync(
            collect($institutionAdminPermissionKeys)
                ->map(fn ($k) => $permissions[$k]?->id)
                ->filter()
                ->values()
                ->all()
        );

        $roleMunicipalityRep->permissions()->sync(
            collect($institutionScopedPermissionKeys)
                ->map(fn ($k) => $permissions[$k]?->id)
                ->filter()
                ->values()
                ->all()
        );

        $roleEmployee->permissions()->sync(
            collect($institutionScopedPermissionKeys)
                ->map(fn ($k) => $permissions[$k]?->id)
                ->filter()
                ->values()
                ->all()
        );

        $roleReporter->permissions()->sync([]);

        $userRoleByEmail = [
            'mantas.kazlauskas@kaunas.lt' => $roleSystemAdmin->id,
            'ieva.petrauskiene@kaunas.lt' => $roleProblemAdmin->id,
            'tomas.jankauskas@kaunas.lt' => $roleInstitutionAdmin->id,
            'rasa.vaitkeviciute@kaunas.lt' => $roleMunicipalityRep->id,
            'lukas.bielskis@kaunas.lt' => $roleEmployee->id,
            'aiste.zukaite@kaunas.lt' => $roleReporter->id,
        ];

        foreach ($userRoleByEmail as $email => $roleId) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                continue;
            }

            $user->roles()->sync([$roleId]);
        }
    }
}
