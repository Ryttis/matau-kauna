<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            if (! Schema::hasColumn('problems', 'institution_id')) {
                $table->foreignId('institution_id')
                    ->nullable()
                    ->after('description')
                    ->constrained('institutions')
                    ->nullOnDelete()
                    ->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            if (Schema::hasColumn('problems', 'institution_id')) {
                $table->dropConstrainedForeignId('institution_id');
            }
        });
    }
};
