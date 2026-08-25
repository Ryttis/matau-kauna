<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'push_notifications_enabled')) {
                $table->boolean('push_notifications_enabled')->default(true)->after('push_registered_at');
            }
            if (! Schema::hasColumn('users', 'push_own_problems')) {
                $table->boolean('push_own_problems')->default(true)->after('push_notifications_enabled');
            }
            if (! Schema::hasColumn('users', 'push_watched_problems')) {
                $table->boolean('push_watched_problems')->default(true)->after('push_own_problems');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['push_watched_problems', 'push_own_problems', 'push_notifications_enabled'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
