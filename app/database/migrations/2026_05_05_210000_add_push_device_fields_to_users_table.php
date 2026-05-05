<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'push_device_id')) {
                $table->string('push_device_id')->nullable()->after('viisp_local_password_set_at');
            }
            if (! Schema::hasColumn('users', 'push_fcm_token')) {
                $table->text('push_fcm_token')->nullable()->after('push_device_id');
            }
            if (! Schema::hasColumn('users', 'push_platform')) {
                $table->string('push_platform', 32)->nullable()->after('push_fcm_token');
            }
            if (! Schema::hasColumn('users', 'push_app_version')) {
                $table->string('push_app_version', 64)->nullable()->after('push_platform');
            }
            if (! Schema::hasColumn('users', 'push_registered_at')) {
                $table->timestamp('push_registered_at')->nullable()->after('push_app_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['push_registered_at', 'push_app_version', 'push_platform', 'push_fcm_token', 'push_device_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
