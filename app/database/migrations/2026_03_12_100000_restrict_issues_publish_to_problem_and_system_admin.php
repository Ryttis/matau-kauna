<?php

use App\Models\UserRole;
use App\Models\UserRolePermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_role_permission_group')) {
            return;
        }

        $publishPermission = UserRolePermission::query()->where('permission', 'issues.publish')->first();
        if (! $publishPermission) {
            return;
        }

        $issuesGroup = DB::table('user_role_permission_group')->where('name', 'Issues')->first();
        if ($issuesGroup) {
            DB::table('user_role_permission_group_map')
                ->where('group_id', $issuesGroup->id)
                ->where('permission_id', $publishPermission->id)
                ->delete();
        }

        $existingPublish = DB::table('user_role_permission_group')->where('name', 'IssuesPublish')->first();
        $publishGroupId = $existingPublish?->id ?? (string) Str::uuid();

        if (! $existingPublish) {
            DB::table('user_role_permission_group')->insert([
                'id' => $publishGroupId,
                'name' => 'IssuesPublish',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('user_role_permission_group_map')->updateOrInsert(
            [
                'permission_id' => $publishPermission->id,
                'group_id' => $publishGroupId,
            ],
            []
        );

        $roleIds = UserRole::query()
            ->whereIn('role', [UserRole::ROLE_PROBLEM_ADMIN, UserRole::ROLE_SYSTEM_ADMIN])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('user_role_permission_group_role_map')->updateOrInsert(
                [
                    'role_id' => $roleId,
                    'group_id' => $publishGroupId,
                ],
                []
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_role_permission_group')) {
            return;
        }

        $publishPermission = UserRolePermission::query()->where('permission', 'issues.publish')->first();
        $issuesGroup = DB::table('user_role_permission_group')->where('name', 'Issues')->first();
        $publishGroup = DB::table('user_role_permission_group')->where('name', 'IssuesPublish')->first();

        if ($publishGroup) {
            DB::table('user_role_permission_group_role_map')->where('group_id', $publishGroup->id)->delete();
            DB::table('user_role_permission_group_map')->where('group_id', $publishGroup->id)->delete();
            DB::table('user_role_permission_group')->where('id', $publishGroup->id)->delete();
        }

        if ($issuesGroup && $publishPermission) {
            DB::table('user_role_permission_group_map')->updateOrInsert(
                [
                    'group_id' => $issuesGroup->id,
                    'permission_id' => $publishPermission->id,
                ],
                []
            );
        }
    }
};
