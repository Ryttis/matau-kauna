<?php

namespace App\Services;

use App\Models\ClassifierValue;
use App\Models\DataRetentionRule;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;

class ArchiveService
{
    public function getIndexData(): array
    {
        $retentionRule = DataRetentionRule::query()
            ->where('entity_type', DataRetentionRule::ENTITY_PROBLEM)
            ->first();

        $users = User::query()
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->get(['id', 'name', 'email', 'archived_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'archived_at' => $u->archived_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $institutions = Institution::query()
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->get(['id', 'name', 'archived_at'])
            ->map(fn (Institution $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'archived_at' => $i->archived_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $problems = Problem::query()
            ->where('is_archived', true)
            ->whereNotNull('archived_at')
            ->whereNull('merged_into_problem_id')
            ->orderByDesc('archived_at')
            ->get(['id', 'internal_number', 'title', 'archived_at'])
            ->map(fn (Problem $p) => [
                'id' => $p->id,
                'internal_number' => $p->internal_number,
                'title' => $p->title,
                'archived_at' => $p->archived_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $classifierValues = ClassifierValue::query()
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->get(['id', 'name', 'parent_id', 'archived_at'])
            ->map(fn (ClassifierValue $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'kind' => $v->parent_id ? 'Pobūdis' : 'Tipas',
                'archived_at' => $v->archived_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'retentionRule' => $retentionRule ? [
                'id' => $retentionRule->id,
                'archive_after_days' => $retentionRule->archive_after_days,
                'delete_after_days' => $retentionRule->delete_after_days,
                'is_active' => $retentionRule->is_active,
            ] : null,
            'users' => $users,
            'institutions' => $institutions,
            'problems' => $problems,
            'classifierValues' => $classifierValues,
        ];
    }

    public function updateRetentionRule(array $data): void
    {
        $days = isset($data['retention_days']) && $data['retention_days'] !== '' ? (int) $data['retention_days'] : null;
        $payload = [
            'archive_after_days' => $days,
            'delete_after_days' => $days,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        DataRetentionRule::query()->updateOrCreate(
            ['entity_type' => DataRetentionRule::ENTITY_PROBLEM],
            $payload
        );
    }
}
