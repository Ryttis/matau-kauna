<?php

use App\Models\ResolvedProblemPrivacySetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resolved_problem_privacy_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('days_after_resolution')->default(ResolvedProblemPrivacySetting::DAYS_AFTER_RESOLUTION_DEFAULT);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        ResolvedProblemPrivacySetting::create([
            'days_after_resolution' => ResolvedProblemPrivacySetting::DAYS_AFTER_RESOLUTION_DEFAULT,
            'is_active' => false,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('resolved_problem_privacy_settings');
    }
};
