<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_gis_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->unique()->constrained('institutions')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('generate_token_url', 512)->nullable();
            $table->string('technical_username', 255)->nullable();
            $table->text('technical_password')->nullable();
            $table->string('client_type', 64)->nullable();
            $table->unsignedInteger('token_expiration_minutes')->nullable();
            $table->string('feature_server_url', 512)->nullable();
            $table->string('attachment_url', 512)->nullable();
            $table->text('active_token')->nullable();
            $table->timestamp('active_token_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_gis_configs');
    }
};
