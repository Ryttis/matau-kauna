<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problem_gis_transfers', function (Blueprint $table) {
            $table->string('external_global_id', 64)->nullable()->after('external_object_id');
            $table->index('external_global_id');
        });

        Schema::table('problem_gis_transfer_attachments', function (Blueprint $table) {
            $table->string('external_global_id', 64)->nullable()->after('external_attachment_id');
        });
    }

    public function down(): void
    {
        Schema::table('problem_gis_transfers', function (Blueprint $table) {
            $table->dropIndex(['external_global_id']);
            $table->dropColumn('external_global_id');
        });

        Schema::table('problem_gis_transfer_attachments', function (Blueprint $table) {
            $table->dropColumn('external_global_id');
        });
    }
};
