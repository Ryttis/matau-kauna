<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->useCurrent();
            $table->string('actor_type', 32)->default('user');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_identifier', 255)->nullable();
            $table->string('event_type', 64)->index();
            $table->string('action', 128)->nullable()->index();
            $table->string('subject_type', 128)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('result', 16)->default('success')->index();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['occurred_at', 'event_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
