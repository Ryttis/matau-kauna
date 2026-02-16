<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_statistics_config', function (Blueprint $table) {
            $table->id();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        DB::table('public_statistics_config')->insert([
            'config' => json_encode([
                'kpis' => ['received_reports', 'avg_resolution_days'],
                'default_period' => 'year',
                'period_options' => ['month', 'year', 'all'],
                'show_category_filter' => true,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('public_statistics_config');
    }
};
