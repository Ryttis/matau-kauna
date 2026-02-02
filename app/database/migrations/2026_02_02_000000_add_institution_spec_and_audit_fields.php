<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Įstaigų lentelė: specifikacijos laukai (tipas, kontaktai, integracija, būsena),
     * audito laukai (created_by, updated_by), is_email_only.
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('type')->nullable()->after('code');
            $table->string('contact_phone')->nullable()->after('type');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->string('contact_address')->nullable()->after('contact_email');
            $table->string('integration_email')->nullable()->after('contact_address');
            $table->string('integration_dvs')->nullable()->after('integration_email');
            $table->string('integration_gis')->nullable()->after('integration_dvs');
            $table->boolean('is_active')->default(true)->after('integration_gis');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->boolean('is_email_only')->default(false)->after('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'type',
                'contact_phone',
                'contact_email',
                'contact_address',
                'integration_email',
                'integration_dvs',
                'integration_gis',
                'is_active',
                'created_by',
                'updated_by',
                'is_email_only',
            ]);
        });
    }
};
