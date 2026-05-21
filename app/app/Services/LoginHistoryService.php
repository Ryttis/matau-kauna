<?php

namespace App\Services;

use App\Models\LoginLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class LoginHistoryService
{
    public function getPaginatedLogs(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $type = $filters['type'] ?? 'internal';

        $query = LoginLog::query()
            ->with('user:id,name,surname,email')
            ->orderByDesc('created_at');

        if ($type === 'reporters') {
            $query->whereHas('user', fn ($q) => $q->whereDoesntHave('roles.permissions', fn ($p) => $p->where('permission', 'issues.view')));
        } else {
            $query->whereHas('user', fn ($q) => $q->whereHas('roles.permissions', fn ($p) => $p->where('permission', 'issues.view')));
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        if (! empty($filters['ip'])) {
            $query->where('ip', 'LIKE', '%'.trim((string) $filters['ip']).'%');
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $query->whereHas('user', function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.$searchLower.'%'])
                    ->orWhereRaw('LOWER(surname) LIKE ?', ['%'.$searchLower.'%'])
                    ->orWhereRaw("LOWER(CONCAT(COALESCE(name, ''), ' ', COALESCE(surname, ''))) LIKE ?", ['%'.$searchLower.'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$searchLower.'%']);
            });
        }

        return $query->paginate($perPage);
    }

    public function mapLogsToArray(iterable $logs): array
    {
        $items = [];
        foreach ($logs as $log) {
            $fullName = trim(implode(' ', array_filter([
                $log->user?->name,
                $log->user?->surname,
            ])));

            $items[] = [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $fullName !== '' ? $fullName : $log->user?->name,
                'user_email' => $log->user?->email,
                'event_type' => $log->event_type,
                'ip' => $log->ip,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        }

        return $items;
    }
}
