<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_COMPLAINT_API = 'gaunami_dokumentai';

    public function up(): void
    {
        if (! Schema::hasColumn('institution_dvs_configs', 'complaint_api')) {
            Schema::table('institution_dvs_configs', function (Blueprint $table) {
                $table->string('complaint_api')
                    ->default(self::DEFAULT_COMPLAINT_API)
                    ->after('enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('institution_dvs_configs', 'complaint_api')) {
            Schema::table('institution_dvs_configs', function (Blueprint $table) {
                $table->dropColumn('complaint_api');
            });
        }
    }
};
