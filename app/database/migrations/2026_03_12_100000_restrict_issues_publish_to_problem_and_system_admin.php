<?php

use App\Models\UserRole;
use App\Models\UserRolePermission;
use App\Models\UserRolePermissionGroup;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $publishPermission = UserRolePermission::query()->where('permission', 'issues.publish')->first();
        if (! $publishPermission) {
            return;
        }

        $issuesGroup = UserRolePermissionGroup::query()->where('name', 'Issues')->first();
        if ($issuesGroup) {
            $issuesGroup->permissions()->detach($publishPermission->id);
        }

        $publishGroup = UserRolePermissionGroup::updateOrCreate(
            ['name' => 'IssuesPublish'],
            []
        );
        $publishGroup->permissions()->sync([$publishPermission->id]);

        $roles = UserRole::query()
            ->whereIn('role', [UserRole::ROLE_PROBLEM_ADMIN, UserRole::ROLE_SYSTEM_ADMIN])
            ->get();
        foreach ($roles as $role) {
            $role->permissionGroups()->syncWithoutDetaching([$publishGroup->id]);
        }
    }

    public function down(): void
    {
        $publishPermission = UserRolePermission::query()->where('permission', 'issues.publish')->first();
        $issuesGroup = UserRolePermissionGroup::query()->where('name', 'Issues')->first();
        $publishGroup = UserRolePermissionGroup::query()->where('name', 'IssuesPublish')->first();

        if ($publishGroup) {
            $publishGroup->roles()->detach();
            $publishGroup->permissions()->detach();
            $publishGroup->delete();
        }

        if ($issuesGroup && $publishPermission) {
            $issuesGroup->permissions()->syncWithoutDetaching([$publishPermission->id]);
        }
    }
};
