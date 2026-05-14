<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_dvs_configs', function (Blueprint $table) {
            $table->string('rusis_kodas')->nullable()->after('rusis_url');
            $table->json('rusys_options')->nullable()->after('rusis_kodas');
        });
    }

    public function down(): void
    {
        Schema::table('institution_dvs_configs', function (Blueprint $table) {
            $table->dropColumn(['rusis_kodas', 'rusys_options']);
        });
    }
};
