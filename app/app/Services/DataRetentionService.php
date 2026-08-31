<?php

namespace App\Services;

use App\Enums\AuditLogResult;
use App\Models\AuditLog;
use App\Models\ClassifierValue;
use App\Models\DataRetentionRule;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use Carbon\Carbon;

class DataRetentionService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function runArchive(): int
    {
        $rule = $this->getActiveProblemRule();
        if (! $rule || ! $rule->delete_after_days || $rule->delete_after_days < 1) {
            return 0;
        }

        $threshold = Carbon::now()->subDays($rule->delete_after_days);

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
            'deadline_status' => null,
            'deadline_reminder_sent_at' => null,
        ]);

        return $ids->count();
    }

    public function runDeletion(): int
    {
        return $this->runDeletionFor(DataRetentionRule::ENTITY_PROBLEM);
    }

    public function countArchived(): int
    {
        $users = User::query()->whereNotNull('archived_at')->count();
        $institutions = Institution::query()->whereNotNull('archived_at')->count();
        $problems = Problem::query()->where('is_archived', true)->whereNotNull('archived_at')->count();
        $classifiers = ClassifierValue::query()->whereNotNull('archived_at')->count();

        return $users + $institutions + $problems + $classifiers;
    }

    public function runDeletionFor(string $entityType): int
    {
        $rule = DataRetentionRule::query()
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->first();
        if (! $rule) {
            return 0;
        }
        $days = $rule->delete_after_days;
        if (! $days || $days < 1) {
            return 0;
        }
        $threshold = Carbon::now()->subDays($days);

        return match ($entityType) {
            DataRetentionRule::ENTITY_USER => $this->deleteArchivedUsers($threshold),
            DataRetentionRule::ENTITY_INSTITUTION => $this->deleteArchivedInstitutions($threshold),
            DataRetentionRule::ENTITY_PROBLEM => $this->deleteArchivedProblems($threshold),
            DataRetentionRule::ENTITY_CLASSIFIER_VALUE => $this->deleteArchivedClassifierValues($threshold),
            default => 0,
        };
    }

    private function deleteArchivedUsers(Carbon $threshold): int
    {
        $query = User::query()
            ->whereNotNull('archived_at')
            ->where('archived_at', '<=', $threshold);
        $count = $query->count();
        $query->delete();

        return $count;
    }

    private function deleteArchivedInstitutions(Carbon $threshold): int
    {
        $query = Institution::query()
            ->whereNotNull('archived_at')
            ->where('archived_at', '<=', $threshold);
        $count = $query->count();
        $query->delete();

        return $count;
    }

    private function deleteArchivedProblems(Carbon $threshold): int
    {
        $seedIds = Problem::query()
            ->where('is_archived', true)
            ->whereNotNull('archived_at')
            ->where('archived_at', '<=', $threshold)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($seedIds === []) {
            return 0;
        }

        // Trinamos tik pačios archyvuotos problemos. Grupės išplėtimas čia kartu
        // įtrauktų ir gyvas, nearchyvuotas grupės nares. Prie trinamos problemos
        // prijungtų problemų nuorodą išvalo pati DB — FK yra nullOnDelete().
        Problem::query()->whereIn('id', $seedIds)->delete();

        return count($seedIds);
    }

    private function deleteArchivedClassifierValues(Carbon $threshold): int
    {
        $query = ClassifierValue::query()
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

        $days = $rule->delete_after_days ?? 0;
        if (! $days || $days < 1) {
            return 0;
        }

        $threshold = Carbon::now()->subDays($days);
        $query = AuditLog::query()->where('occurred_at', '<', $threshold);
        try {
            $count = $query->count();
            $query->delete();
            if ($count > 0) {
                $this->auditLogService->logSystem('audit_retention.purge', AuditLogResult::Success, null, ['deleted_count' => $count]);
            }

            return $count;
        } catch (\Throwable $e) {
            $this->auditLogService->logSystem('audit_retention.purge', AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    private function getActiveProblemRule(): ?DataRetentionRule
    {
        return DataRetentionRule::query()
            ->where('entity_type', DataRetentionRule::ENTITY_PROBLEM)
            ->where('is_active', true)
            ->first();
    }
}
