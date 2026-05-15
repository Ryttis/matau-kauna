<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'gaunami_failu_registro_kodas',
        'pilieciu_failu_registro_kodas',
    ];

    public function up(): void
    {
        Schema::table('institution_dvs_configs', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (! Schema::hasColumn('institution_dvs_configs', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('institution_dvs_configs', function (Blueprint $table) {
            foreach (array_reverse(self::COLUMNS) as $column) {
                if (Schema::hasColumn('institution_dvs_configs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
