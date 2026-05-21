<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginHistoryIndexRequest;
use App\Services\LoginHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoginHistoryController extends Controller
{
    public function __construct(
        private LoginHistoryService $loginHistoryService
    ) {}

    public function index(LoginHistoryIndexRequest $request): JsonResponse
    {
        try {
            $logs = $this->loginHistoryService->getPaginatedLogs($request->validated(), 50);
            $items = $this->loginHistoryService->mapLogsToArray($logs->getCollection());

            return response()->json([
                'data' => $items,
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]);
        } catch (Throwable $e) {
            Log::error('Prisijungimų istorija: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Nepavyko užkrauti prisijungimų istorijos.',
            ], 500);
        }
    }
}
