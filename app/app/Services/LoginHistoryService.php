<?php

namespace App\Services;

use App\Models\LoginLog;
use App\Models\UserRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoginHistoryService
{
    public function getPaginatedLogs(string $type = 'internal', string $search = '', int $perPage = 50): LengthAwarePaginator
    {
        $query = LoginLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($type === 'reporters') {
            $query->whereHas('user', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('role', UserRole::ROLE_REPORTER)));
        } else {
            $query->whereHas('user', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('role', '!=', UserRole::ROLE_REPORTER)));
        }

        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $query->whereHas('user', function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.$searchLower.'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$searchLower.'%']);
            });
        }

        return $query->paginate($perPage);
    }

    public function mapLogsToArray(iterable $logs): array
    {
        $items = [];
        foreach ($logs as $log) {
            $items[] = [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name,
                'user_email' => $log->user?->email,
                'event_type' => $log->event_type,
                'ip' => $log->ip,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        }

        return $items;
    }
}
