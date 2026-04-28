<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problem_attachments', function (Blueprint $table) {
            $table->string('source', 32)->nullable()->after('kind');
        });

        Schema::table('problem_gis_transfer_attachments', function (Blueprint $table) {
            $table->string('direction', 16)->default('outbound')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('problem_attachments', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('problem_gis_transfer_attachments', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
