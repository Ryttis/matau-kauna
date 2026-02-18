<?php

use App\Models\DataRetentionRule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (DataRetentionRule::query()->where('entity_type', DataRetentionRule::ENTITY_AUDIT_LOG)->exists()) {
            return;
        }
        DataRetentionRule::create([
            'entity_type' => DataRetentionRule::ENTITY_AUDIT_LOG,
            'archive_after_days' => null,
            'delete_after_days' => 365,
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        DataRetentionRule::query()
            ->where('entity_type', DataRetentionRule::ENTITY_AUDIT_LOG)
            ->delete();
    }
};
