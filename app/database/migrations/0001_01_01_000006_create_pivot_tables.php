<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // user <-> roles
        Schema::create('user_role_map', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->uuid('role_id');
            $table->primary(['user_id', 'role_id']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('user_role')->cascadeOnDelete();
        });

        // permission <-> group
        Schema::create('user_role_permission_group_map', function (Blueprint $table) {
            $table->uuid('permission_id');
            $table->uuid('group_id');
            $table->primary(['permission_id', 'group_id']);

            $table->foreign('permission_id')->references('id')->on('user_role_permission')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('user_role_permission_group')->cascadeOnDelete();
        });

        // role <-> group
        Schema::create('user_role_permission_group_role_map', function (Blueprint $table) {
            $table->uuid('role_id');
            $table->uuid('group_id');
            $table->primary(['role_id', 'group_id']);

            $table->foreign('role_id')->references('id')->on('user_role')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('user_role_permission_group')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_permission_group_role_map');
        Schema::dropIfExists('user_role_permission_group_map');
        Schema::dropIfExists('user_role_map');
    }
};
