<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('role');
            $table->string('name');
            $table->timestamps();

            $table->unique('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role');
    }
};
