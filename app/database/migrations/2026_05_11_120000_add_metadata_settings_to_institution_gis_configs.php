<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_gis_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('institution_gis_configs', 'metadata_url')) {
                $table->string('metadata_url', 512)->nullable()->after('attachment_url');
            }

            if (! Schema::hasColumn('institution_gis_configs', 'metadata_layer_id')) {
                $table->string('metadata_layer_id', 64)->nullable()->after('metadata_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('institution_gis_configs', function (Blueprint $table) {
            if (Schema::hasColumn('institution_gis_configs', 'metadata_layer_id')) {
                $table->dropColumn('metadata_layer_id');
            }

            if (Schema::hasColumn('institution_gis_configs', 'metadata_url')) {
                $table->dropColumn('metadata_url');
            }
        });
    }
};
