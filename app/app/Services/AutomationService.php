<?php

namespace App\Services;

use App\Enums\ProblemStatus;
use App\Models\ClassifierValue;
use App\Models\InactiveReporterDeletionSetting;
use App\Models\Institution;
use App\Models\LoginLog;
use App\Models\ProblemAutoAssignmentRule;
use App\Models\ProblemAutoPublishRule;
use App\Models\ProblemResolutionTermRule;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Carbon;

class AutomationService
{
    public function getIndexData(): array
    {
        $rules = ProblemAutoAssignmentRule::query()
            ->with('institution:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (ProblemAutoAssignmentRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'category' => $r->category,
                'institution_id' => $r->institution_id,
                'institution_name' => $r->institution?->name,
                'is_active' => $r->is_active,
            ]);

        $categories = ClassifierValue::getActiveTypeNames()->values()->all();
        $institutions = Institution::query()->notArchived()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $publishRules = ProblemAutoPublishRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn (ProblemAutoPublishRule $r) => [
                'id' => $r->id,
                'category' => $r->category,
                'status' => $r->status,
                'is_active' => $r->is_active,
            ]);

        $resolutionTermRules = ProblemResolutionTermRule::query()
            ->with(['institution:id,name', 'problemType:id,name', 'problemNature:id,name'])
            ->orderBy('id')
            ->get()
            ->map(fn (ProblemResolutionTermRule $r) => [
                'id' => $r->id,
                'days' => $r->days,
                'institution_id' => $r->institution_id,
                'institution_name' => $r->institution?->name,
                'category' => $r->category,
                'problem_type_id' => $r->problem_type_id,
                'problem_type_name' => $r->problemType?->name,
                'problem_nature_id' => $r->problem_nature_id,
                'problem_nature_name' => $r->problemNature?->name,
                'is_active' => $r->is_active,
            ]);

        $classifierTypesWithNatures = ClassifierValue::notArchived()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['activeChildren:id,parent_id,name'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'natures' => $t->activeChildren->map(fn ($n) => ['id' => $n->id, 'name' => $n->name])->values()->all(),
            ])
            ->values()
            ->all();

        $inactiveReporterSetting = InactiveReporterDeletionSetting::firstOrCreateDefault();

        return [
            'rules' => $rules,
            'categories' => $categories,
            'institutions' => $institutions,
            'publishRules' => $publishRules,
            'resolutionTermRules' => $resolutionTermRules,
            'classifierTypesWithNatures' => $classifierTypesWithNatures,
            'statusOptions' => ProblemStatus::options(),
            'inactiveReporterSetting' => [
                'id' => $inactiveReporterSetting->id,
                'inactivity_days' => $inactiveReporterSetting->inactivity_days,
                'reminder_days' => $inactiveReporterSetting->reminderDays(),
                'is_active' => $inactiveReporterSetting->is_active,
                'inactivity_days_min' => InactiveReporterDeletionSetting::INACTIVITY_DAYS_MIN,
                'inactivity_days_max' => InactiveReporterDeletionSetting::INACTIVITY_DAYS_MAX,
                'inactivity_days_default' => InactiveReporterDeletionSetting::INACTIVITY_DAYS_DEFAULT,
            ],
            'inactiveReportersList' => $this->getInactiveReportersList(),
        ];
    }

    public function getInactiveReportersList(): array
    {
        $users = User::query()
            ->notArchived()
            ->whereHas('roles', fn ($q) => $q->where('role', UserRole::ROLE_REPORTER))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('role', [
                UserRole::ROLE_SYSTEM_ADMIN,
                UserRole::ROLE_PROBLEM_ADMIN,
                UserRole::ROLE_INSTITUTION_ADMIN,
                UserRole::ROLE_MUNICIPALITY_REP,
                UserRole::ROLE_EMPLOYEE,
            ]))
            ->get(['id', 'name', 'surname', 'email', 'last_login_at']);

        $list = [];
        foreach ($users as $user) {
            $lastLoginAt = $user->last_login_at;
            if ($lastLoginAt === null) {
                $fromLog = LoginLog::where('user_id', $user->id)
                    ->where('event_type', LoginLog::EVENT_LOGIN)
                    ->orderByDesc('created_at')
                    ->value('created_at');
                $lastLoginAt = $fromLog ? Carbon::parse($fromLog) : null;
            }
            $inactiveDays = $lastLoginAt
                ? (int) $lastLoginAt->diffInDays(now(), false)
                : null;

            $list[] = [
                'id' => $user->id,
                'name' => trim($user->name . ' ' . ($user->surname ?? '')) ?: $user->email,
                'email' => $user->email,
                'last_login_at' => $lastLoginAt?->format('Y-m-d H:i'),
                'inactive_days' => $inactiveDays,
            ];
        }

        usort($list, fn ($a, $b) => ($b['inactive_days'] ?? -1) <=> ($a['inactive_days'] ?? -1));

        return $list;
    }

    public function normalizeAssignmentRuleData(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        if (isset($data['category']) && $data['category'] !== null) {
            $data['category'] = trim($data['category']) ?: null;
        }

        return $data;
    }

    public function normalizePublishRuleData(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        if (array_key_exists('category', $data) && $data['category'] !== null) {
            $data['category'] = trim($data['category']) ?: null;
        }
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $data['status'] = trim($data['status']) ?: null;
        }

        return $data;
    }

    public function normalizeResolutionTermRuleData(array $data): array
    {
        $data['days'] = (int) $data['days'];
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['institution_id'] = ! empty($data['institution_id']) ? (int) $data['institution_id'] : null;
        $data['problem_type_id'] = ! empty($data['problem_type_id']) ? (int) $data['problem_type_id'] : null;
        $data['problem_nature_id'] = ! empty($data['problem_nature_id']) ? (int) $data['problem_nature_id'] : null;
        if (array_key_exists('category', $data) && $data['category'] !== null) {
            $data['category'] = trim($data['category']) ?: null;
        }

        return $data;
    }
}
