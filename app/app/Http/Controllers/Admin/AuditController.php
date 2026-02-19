<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditExportRequest;
use App\Http\Requests\Admin\AuditIndexRequest;
use App\Http\Requests\Admin\AuditUpdateSettingsRequest;
use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function __construct(
        private AuditService $auditService
    ) {}

    public function index(AuditIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $this->auditService->getFilteredLogs($validated, $perPage);
        $items = $this->auditService->mapLogsToArray($paginator->getCollection());

        return response()->json([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function export(AuditExportRequest $request): StreamedResponse
    {
        $query = $this->auditService->buildExportQuery($request->validated());
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
        return response()->json($this->auditService->getSettings());
    }

    public function updateSettings(AuditUpdateSettingsRequest $request): JsonResponse
    {
        $result = $this->auditService->updateSettings($request->validated(), $request->user());

        return response()->json($result);
    }
}
