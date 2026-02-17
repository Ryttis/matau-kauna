<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type', 'internal');
        $search = $request->input('search', '')->trim();

        $query = LoginLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($type === 'reporters') {
            $query->whereHas('user', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('role', UserRole::ROLE_REPORTER)));
        } else {
            $query->whereHas('user', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('role', '!=', UserRole::ROLE_REPORTER)));
        }

        if ($search !== '') {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $logs = $query->paginate(50);

        $items = $logs->getCollection()->map(fn ($log) => [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'user_name' => $log->user?->name,
            'user_email' => $log->user?->email,
            'event_type' => $log->event_type,
            'ip' => $log->ip,
            'created_at' => $log->created_at->toIso8601String(),
        ])->all();

        return response()->json([
            'data' => $items,
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
        ]);
    }
}
