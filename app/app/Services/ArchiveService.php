<?php

namespace App\Services;

use App\Models\ClassifierValue;
use App\Models\DataRetentionRule;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use Carbon\Carbon;

class ArchiveService
{
    private const ENTITY_TYPES = [
        DataRetentionRule::ENTITY_USER,
        DataRetentionRule::ENTITY_INSTITUTION,
        DataRetentionRule::ENTITY_PROBLEM,
        DataRetentionRule::ENTITY_CLASSIFIER_VALUE,
    ];

    public function getIndexData(): array
    {
        $rules = DataRetentionRule::query()
            ->whereIn('entity_type', self::ENTITY_TYPES)
            ->get()
            ->keyBy('entity_type');

        $userRule = $rules->get(DataRetentionRule::ENTITY_USER);
        $instRule = $rules->get(DataRetentionRule::ENTITY_INSTITUTION);
        $problemRule = $rules->get(DataRetentionRule::ENTITY_PROBLEM);
        $classifierRule = $rules->get(DataRetentionRule::ENTITY_CLASSIFIER_VALUE);

        $users = User::query()
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->get(['id', 'name', 'email', 'archived_at'])
            ->map(fn (User $u) => $this->withDeleteAt($u->archived_at, $userRule, [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'archived_at' => $u->archived_at?->toIso8601String(),
            ]))
            ->values()
            ->all();

        $institutions = Institution::query()
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->get(['id', 'name', 'archived_at'])
            ->map(fn (Institution $i) => $this->withDeleteAt($i->archived_at, $instRule, [
                'id' => $i->id,
                'name' => $i->name,
                'archived_at' => $i->archived_at?->toIso8601String(),
            ]))
            ->values()
            ->all();

        $problems = Problem::query()
            ->where('is_archived', true)
            ->whereNotNull('archived_at')
            ->whereNull('merged_into_problem_id')
            ->orderByDesc('archived_at')
            ->get(['id', 'internal_number', 'description', 'archived_at'])
            ->map(fn (Problem $p) => $this->withDeleteAt($p->archived_at, $problemRule, [
                'id' => $p->id,
                'internal_number' => $p->internal_number,
                'description_preview' => Problem::descriptionPreview($p->description, 120),
                'archived_at' => $p->archived_at?->toIso8601String(),
            ]))
            ->values()
            ->all();

        $classifierValues = ClassifierValue::query()
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->get(['id', 'name', 'parent_id', 'archived_at'])
            ->map(fn (ClassifierValue $v) => $this->withDeleteAt($v->archived_at, $classifierRule, [
                'id' => $v->id,
                'name' => $v->name,
                'kind' => $v->parent_id ? 'Pobūdis' : 'Tipas',
                'archived_at' => $v->archived_at?->toIso8601String(),
            ]))
            ->values()
            ->all();

        $retentionRules = [];
        foreach (self::ENTITY_TYPES as $type) {
            $rule = $rules->get($type);
            $retentionRules[$type] = $rule ? [
                'id' => $rule->id,
                'delete_after_days' => $rule->delete_after_days,
                'is_active' => $rule->is_active,
            ] : null;
        }

        return [
            'retentionRules' => $retentionRules,
            'users' => $users,
            'institutions' => $institutions,
            'problems' => $problems,
            'classifierValues' => $classifierValues,
        ];
    }

    private function withDeleteAt($archivedAt, ?DataRetentionRule $rule, array $row): array
    {
        $days = $rule?->delete_after_days;
        if (! $archivedAt || ! $days || $days < 1) {
            $row['delete_at'] = null;

            return $row;
        }
        $deleteAt = Carbon::parse($archivedAt)->addDays($days);
        $row['delete_at'] = $deleteAt->toIso8601String();

        return $row;
    }

    public function updateRetentionRule(array $data): void
    {
        $entityType = $data['entity_type'] ?? DataRetentionRule::ENTITY_PROBLEM;
        if (! in_array($entityType, self::ENTITY_TYPES, true)) {
            return;
        }
        $days = isset($data['retention_days']) && $data['retention_days'] !== '' ? (int) $data['retention_days'] : null;
        $payload = [
            'delete_after_days' => $days,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
        DataRetentionRule::query()->updateOrCreate(
            ['entity_type' => $entityType],
            $payload
        );
    }
}
