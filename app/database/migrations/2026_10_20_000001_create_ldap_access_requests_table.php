<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ldap_access_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ldap_source', 64);
            $table->string('ldap_username', 255);
            $table->text('directory_name')->nullable();
            $table->text('directory_email')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('linked_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['ldap_source', 'ldap_username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ldap_access_requests');
    }
};
