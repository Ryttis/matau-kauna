<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DataRetentionRule;
use App\Models\Problem;
use App\Services\AuditLogService;
use Carbon\Carbon;

class DataRetentionService
{
    public function runArchive(): int
    {
        $rule = $this->getActiveProblemRule();
        if (! $rule || ! $rule->archive_after_days || $rule->archive_after_days < 1) {
            return 0;
        }

        $threshold = Carbon::now()->subDays($rule->archive_after_days);

        $ids = Problem::query()
            ->where('status', 'isspresta')
            ->where('is_archived', false)
            ->whereNull('merged_into_problem_id')
            ->where('status_updated_at', '<=', $threshold)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        Problem::whereIn('id', $ids)->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        return $ids->count();
    }

    public function runDeletion(): int
    {
        $rule = $this->getActiveProblemRule();
        if (! $rule) {
            return 0;
        }

        $days = $rule->delete_after_days ?? $rule->archive_after_days;
        if (! $days || $days < 1) {
            return 0;
        }

        $threshold = Carbon::now()->subDays($days);
        $query = Problem::query()
            ->where('is_archived', true)
            ->whereNotNull('archived_at')
            ->where('archived_at', '<=', $threshold);

        $count = $query->count();
        $query->delete();

        return $count;
    }

    public function runAuditLogPurge(): int
    {
        $rule = DataRetentionRule::query()
            ->where('entity_type', DataRetentionRule::ENTITY_AUDIT_LOG)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            return 0;
        }

        $days = $rule->delete_after_days ?? $rule->archive_after_days ?? 0;
        if (! $days || $days < 1) {
            return 0;
        }

        $threshold = Carbon::now()->subDays($days);
        $query = AuditLog::query()->where('occurred_at', '<', $threshold);
        $count = $query->count();
        $query->delete();

        if ($count > 0) {
            app(AuditLogService::class)->logSystem('audit_retention.purge', AuditLog::RESULT_SUCCESS, null, ['deleted_count' => $count]);
        }

        return $count;
    }

    private function getActiveProblemRule(): ?DataRetentionRule
    {
        return DataRetentionRule::query()
            ->where('entity_type', DataRetentionRule::ENTITY_PROBLEM)
            ->where('is_active', true)
            ->first();
    }
}
