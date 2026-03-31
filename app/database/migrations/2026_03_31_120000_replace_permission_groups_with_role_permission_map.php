<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_role_permission_map')) {
            Schema::create('user_role_permission_map', function (Blueprint $table) {
                $table->uuid('role_id');
                $table->uuid('permission_id');
                $table->primary(['role_id', 'permission_id']);

                $table->foreign('role_id')->references('id')->on('user_role')->cascadeOnDelete();
                $table->foreign('permission_id')->references('id')->on('user_role_permission')->cascadeOnDelete();
            });
        }

        if (
            Schema::hasTable('user_role_permission_group_role_map')
            && Schema::hasTable('user_role_permission_group_map')
        ) {
            $rows = DB::table('user_role_permission_group_role_map as rg')
                ->join('user_role_permission_group_map as gp', 'gp.group_id', '=', 'rg.group_id')
                ->select('rg.role_id', 'gp.permission_id')
                ->distinct()
                ->get();

            foreach ($rows as $row) {
                DB::table('user_role_permission_map')->updateOrInsert(
                    [
                        'role_id' => $row->role_id,
                        'permission_id' => $row->permission_id,
                    ],
                    []
                );
            }
        }

        Schema::dropIfExists('user_role_permission_group_role_map');
        Schema::dropIfExists('user_role_permission_group_map');
        Schema::dropIfExists('user_role_permission_group');
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_role_permission_group')) {
            Schema::create('user_role_permission_group', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_role_permission_group_map')) {
            Schema::create('user_role_permission_group_map', function (Blueprint $table) {
                $table->uuid('permission_id');
                $table->uuid('group_id');
                $table->primary(['permission_id', 'group_id']);

                $table->foreign('permission_id')->references('id')->on('user_role_permission')->cascadeOnDelete();
                $table->foreign('group_id')->references('id')->on('user_role_permission_group')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('user_role_permission_group_role_map')) {
            Schema::create('user_role_permission_group_role_map', function (Blueprint $table) {
                $table->uuid('role_id');
                $table->uuid('group_id');
                $table->primary(['role_id', 'group_id']);

                $table->foreign('role_id')->references('id')->on('user_role')->cascadeOnDelete();
                $table->foreign('group_id')->references('id')->on('user_role_permission_group')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('user_role_permission_map')) {
            $roleIds = DB::table('user_role')->pluck('id');
            foreach ($roleIds as $roleId) {
                $groupId = (string) Str::uuid();

                DB::table('user_role_permission_group')->insert([
                    'id' => $groupId,
                    'name' => 'Migrated '.$roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('user_role_permission_group_role_map')->insert([
                    'role_id' => $roleId,
                    'group_id' => $groupId,
                ]);

                $permissionIds = DB::table('user_role_permission_map')
                    ->where('role_id', $roleId)
                    ->pluck('permission_id');

                foreach ($permissionIds as $permissionId) {
                    DB::table('user_role_permission_group_map')->insert([
                        'permission_id' => $permissionId,
                        'group_id' => $groupId,
                    ]);
                }
            }

            Schema::dropIfExists('user_role_permission_map');
        }
    }
};

