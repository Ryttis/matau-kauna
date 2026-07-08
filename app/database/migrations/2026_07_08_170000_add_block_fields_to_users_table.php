<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('archived_at');
            $table->foreignId('blocked_by')->nullable()->after('blocked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('unblocked_at')->nullable()->after('blocked_by');
            $table->foreignId('unblocked_by')->nullable()->after('unblocked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unblocked_by');
            $table->dropColumn('unblocked_at');
            $table->dropConstrainedForeignId('blocked_by');
            $table->dropColumn('blocked_at');
        });
    }
};
