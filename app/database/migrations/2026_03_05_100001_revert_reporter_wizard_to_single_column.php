<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'reporter_wizard_completed_at_1')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('reporter_wizard_completed_at')->nullable()->after('terms_accepted_at');
        });

        DB::table('users')
            ->whereNotNull('reporter_wizard_completed_at_1')
            ->orWhereNotNull('reporter_wizard_completed_at_2')
            ->update([
                'reporter_wizard_completed_at' => DB::raw('COALESCE(reporter_wizard_completed_at_1, reporter_wizard_completed_at_2)'),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reporter_wizard_completed_at_1', 'reporter_wizard_completed_at_2']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'reporter_wizard_completed_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('reporter_wizard_completed_at_1')->nullable()->after('reporter_wizard_completed_at');
            $table->timestamp('reporter_wizard_completed_at_2')->nullable()->after('reporter_wizard_completed_at_1');
        });

        DB::table('users')
            ->whereNotNull('reporter_wizard_completed_at')
            ->update([
                'reporter_wizard_completed_at_1' => DB::raw('reporter_wizard_completed_at'),
                'reporter_wizard_completed_at_2' => DB::raw('reporter_wizard_completed_at'),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('reporter_wizard_completed_at');
        });
    }
};

