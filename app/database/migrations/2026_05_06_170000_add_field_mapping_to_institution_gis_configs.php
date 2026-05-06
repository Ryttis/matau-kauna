<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_gis_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('institution_gis_configs', 'use_field_mapping')) {
                $table->boolean('use_field_mapping')->default(false)->after('wkid');
            }

            if (! Schema::hasColumn('institution_gis_configs', 'field_mapping')) {
                $table->json('field_mapping')->nullable()->after('use_field_mapping');
            }
        });
    }

    public function down(): void
    {
        Schema::table('institution_gis_configs', function (Blueprint $table) {
            if (Schema::hasColumn('institution_gis_configs', 'field_mapping')) {
                $table->dropColumn('field_mapping');
            }

            if (Schema::hasColumn('institution_gis_configs', 'use_field_mapping')) {
                $table->dropColumn('use_field_mapping');
            }
        });
    }
};
