<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_upload_settings', function (Blueprint $table) {
            $table->id();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        $defaultConfig = [
            'web' => [
                'allow_photo' => true,
                'allow_video' => false,
                'max_photo_size_kb' => 5120,
                'max_video_size_kb' => 51200,
                'max_photo_files_per_problem' => 5,
                'max_video_files_per_problem' => 1,
                'max_total_upload_kb' => 51200,
                'description_max_chars' => 65535,
            ],
            'mobile' => [
                'allow_photo' => true,
                'allow_video' => false,
                'max_photo_size_kb' => 5120,
                'max_video_size_kb' => 51200,
                'max_photo_files_per_problem' => 5,
                'max_video_files_per_problem' => 1,
                'max_total_upload_kb' => 51200,
                'description_max_chars' => 65535,
            ],
        ];

        DB::table('file_upload_settings')->insert([
            'config' => json_encode($defaultConfig),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('problem_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->constrained('problems')->cascadeOnDelete();
            $table->string('path', 512);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 128);
            $table->unsignedInteger('size_bytes');
            $table->string('kind', 16);
            $table->timestamps();

            $table->index('problem_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_attachments');
        Schema::dropIfExists('file_upload_settings');
    }
};
