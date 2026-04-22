<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('file_upload_settings')->first();
        if ($row === null) {
            return;
        }

        $cfg = json_decode($row->config, true) ?? [];

        foreach (['web', 'mobile'] as $ch) {
            if (! isset($cfg[$ch]) || ! is_array($cfg[$ch])) {
                continue;
            }

            $cfg[$ch]['max_photo_size_kb'] = (int) ($cfg[$ch]['max_photo_size_kb'] ?? 5120);
            unset(
                $cfg[$ch]['max_video_size_kb'],
                $cfg[$ch]['max_total_upload_kb'],
                $cfg[$ch]['max_files_per_problem']
            );

            if (! isset($cfg[$ch]['max_video_duration_seconds'])) {
                $cfg[$ch]['max_video_duration_seconds'] = 60;
            }
        }

        DB::table('file_upload_settings')->where('id', $row->id)->update([
            'config' => json_encode($cfg),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $row = DB::table('file_upload_settings')->first();
        if ($row === null) {
            return;
        }

        $cfg = json_decode($row->config, true) ?? [];

        foreach (['web', 'mobile'] as $ch) {
            if (! isset($cfg[$ch]) || ! is_array($cfg[$ch])) {
                continue;
            }

            $cfg[$ch]['max_photo_size_kb'] = 5120;
            $cfg[$ch]['max_video_size_kb'] = 51200;
            $cfg[$ch]['max_total_upload_kb'] = 51200;
        }

        DB::table('file_upload_settings')->where('id', $row->id)->update([
            'config' => json_encode($cfg),
            'updated_at' => now(),
        ]);
    }
};
