<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\AuditSetting;
use App\Models\DataRetentionRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AuditService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function getFilteredLogs(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = AuditLog::query()->orderByDesc('occurred_at');
        $this->applyLogFilters($query, $filters);

        return $query->paginate($perPage);
    }

    public function buildExportQuery(array $filters): Builder
    {
        $query = AuditLog::query()->orderByDesc('occurred_at');
        $this->applyLogFilters($query, $filters);

        return $query;
    }

    public function mapLogsToArray(Collection $logs): array
    {
        return $logs->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'occurred_at' => $log->occurred_at->toIso8601String(),
            'actor_type' => $log->actor_type,
            'actor_identifier' => $log->actor_identifier,
            'event_type' => $log->event_type,
            'action' => $log->action,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'result' => $log->result,
            'message' => $log->message,
            'metadata' => $log->metadata,
        ])->all();
    }

    public function getSettings(): array
    {
        $enabled = AuditSetting::isEnabled();
        $retentionRule = DataRetentionRule::query()
            ->where('entity_type', DataRetentionRule::ENTITY_AUDIT_LOG)
            ->first();

        return [
            'enabled' => $enabled,
            'retention_days' => $retentionRule?->delete_after_days,
            'retention_is_active' => $retentionRule?->is_active ?? false,
        ];
    }

    public function updateSettings(array $data, $user): array
    {
        if (array_key_exists('enabled', $data)) {
            $enabled = (bool) $data['enabled'];
            AuditSetting::setEnabled($enabled);
            $this->auditLogService->logAuditConfig(
                $user,
                'audit_config.toggle',
                'success',
                $enabled ? 'Žurnalavimas įjungtas.' : 'Žurnalavimas išjungtas.'
            );
        }

        $retentionRule = DataRetentionRule::query()
            ->where('entity_type', DataRetentionRule::ENTITY_AUDIT_LOG)
            ->first();

        if (array_key_exists('retention_days', $data) || array_key_exists('retention_is_active', $data)) {
            $payload = [
                'entity_type' => DataRetentionRule::ENTITY_AUDIT_LOG,
                'archive_after_days' => null,
                'delete_after_days' => $data['retention_days'] ?? $retentionRule?->delete_after_days ?? 365,
                'is_active' => (bool) ($data['retention_is_active'] ?? $retentionRule?->is_active ?? true),
            ];
            if ($retentionRule) {
                $retentionRule->update($payload);
            } else {
                DataRetentionRule::create($payload);
            }
        }

        $updated = DataRetentionRule::query()
            ->where('entity_type', DataRetentionRule::ENTITY_AUDIT_LOG)
            ->first();

        return [
            'enabled' => AuditSetting::isEnabled(),
            'retention_days' => $updated?->delete_after_days ?? 365,
            'retention_is_active' => $updated?->is_active ?? true,
        ];
    }

    private function applyLogFilters(Builder $query, array $filters): void
    {
        $query->occurredBetween($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        if (! empty($filters['event_type'])) {
            $query->eventType($filters['event_type']);
        }
        if (! empty($filters['result'])) {
            $query->result($filters['result']);
        }
        $query->search($filters['search'] ?? null);
    }
}
