<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AuditSetting;
use App\Models\DataRetentionRule;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'event_type' => ['nullable', 'string', 'in:login,logout,user_action,integration,system,audit_config'],
            'result' => ['nullable', 'string', 'in:success,failure'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = AuditLog::query()
            ->orderByDesc('occurred_at');

        $query->occurredBetween(
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null
        );

        if (! empty($validated['event_type'])) {
            $query->eventType($validated['event_type']);
        }
        if (! empty($validated['result'])) {
            $query->result($validated['result']);
        }
        $query->search($validated['search'] ?? null);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(fn (AuditLog $log) => [
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

        return response()->json([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'event_type' => ['nullable', 'string', 'in:login,logout,user_action,integration,system,audit_config'],
            'result' => ['nullable', 'string', 'in:success,failure'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = AuditLog::query()
            ->orderByDesc('occurred_at')
            ->occurredBetween($validated['date_from'] ?? null, $validated['date_to'] ?? null);

        if (! empty($validated['event_type'])) {
            $query->eventType($validated['event_type']);
        }
        if (! empty($validated['result'])) {
            $query->result($validated['result']);
        }
        $query->search($validated['search'] ?? null);

        $filename = 'audito-zurnalas-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'Data ir laikas', 'Atlikėjas', 'Įvykio tipas', 'Veiksmas', 'Subjektas', 'Rezultatas', 'Žinutė', 'Metadata',
            ], ';');

            foreach ($query->cursor() as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->occurred_at->toIso8601String(),
                    $log->actor_identifier ?? '',
                    $log->event_type,
                    $log->action ?? '',
                    ($log->subject_type ?? '') . ($log->subject_id ? '#' . $log->subject_id : ''),
                    $log->result,
                    $log->message ?? '',
                    $log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_UNICODE) : '',
                ], ';');
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function settings(): JsonResponse
    {
        $enabled = AuditSetting::isEnabled();
        $retentionRule = DataRetentionRule::query()
            ->where('entity_type', DataRetentionRule::ENTITY_AUDIT_LOG)
            ->first();
        return response()->json([
            'enabled' => $enabled,
            'retention_days' => $retentionRule?->delete_after_days,
            'retention_is_active' => $retentionRule?->is_active ?? false,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'retention_is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('enabled', $data)) {
            $enabled = (bool) $data['enabled'];
            AuditSetting::setEnabled($enabled);
            app(AuditLogService::class)->logAuditConfig(
                $request->user(),
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

        return response()->json([
            'enabled' => AuditSetting::isEnabled(),
            'retention_days' => $updated?->delete_after_days ?? 365,
            'retention_is_active' => $updated?->is_active ?? true,
        ]);
    }
}
