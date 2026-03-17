<?php

use App\Models\InactiveReporterDeletionSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inactive_reporter_deletion_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('inactivity_days')->default(InactiveReporterDeletionSetting::INACTIVITY_DAYS_DEFAULT);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        InactiveReporterDeletionSetting::create([
            'inactivity_days' => InactiveReporterDeletionSetting::INACTIVITY_DAYS_DEFAULT,
            'is_active' => false,
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->timestamp('inactive_reporter_reminder_sent_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inactive_reporter_deletion_settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'inactive_reporter_reminder_sent_at']);
        });
    }
};
