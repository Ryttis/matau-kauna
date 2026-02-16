<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->string('deadline_status', 20)->nullable()->after('completion_deadline');
            $table->timestamp('deadline_reminder_sent_at')->nullable()->after('deadline_status');
        });
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropColumn(['deadline_status', 'deadline_reminder_sent_at']);
        });
    }
};
