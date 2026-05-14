<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'gaunami_registro_kodas',
        'gaunami_registro_id',
        'gaunami_nukreipto_id',
        'gaunami_sudarytojas_id',
        'pilieciu_registro_kodas',
        'pilieciu_registro_id',
        'pilieciu_nukreipto_id',
        'pilieciu_sudarytojas_id',
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

        DB::table('institution_dvs_configs')->update([
            'gaunami_registro_kodas' => DB::raw('registras_id'),
            'gaunami_nukreipto_id' => DB::raw('darbuotojo_kodas'),
            'gaunami_sudarytojas_id' => DB::raw('sudarytojas_kodas'),
        ]);
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
