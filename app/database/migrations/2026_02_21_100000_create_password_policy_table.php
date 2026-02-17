<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_policy', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('min_length')->default(8);
            $table->boolean('require_lowercase')->default(true);
            $table->boolean('require_uppercase')->default(true);
            $table->boolean('require_special')->default(true);
            $table->boolean('two_factor_can_enable')->default(true);
            $table->boolean('prohibit_name_email')->default(true);
            $table->timestamps();
        });

        \Illuminate\Support\Facades\DB::table('password_policy')->insert([
            'min_length' => 8,
            'require_lowercase' => true,
            'require_uppercase' => true,
            'require_special' => true,
            'two_factor_can_enable' => true,
            'prohibit_name_email' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('password_policy');
    }
};
