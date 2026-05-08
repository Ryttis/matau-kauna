<?php

namespace App\Services;

use App\Enums\ProblemStatus;
use App\Models\ClassifierValue;
use App\Models\InactiveReporterDeletionSetting;
use App\Models\Institution;
use App\Models\LoginLog;
use App\Models\Problem;
use App\Models\ProblemAutoAssignmentRule;
use App\Models\ProblemAutoPublishRule;
use App\Models\ProblemResolutionTermRule;
use App\Models\ResolvedProblemPrivacySetting;
use App\Models\User;
use Illuminate\Support\Carbon;

class AutomationService
{
    public function getIndexData(): array
    {
        return [
            'rules' => $this->getAssignmentRules(),
            'categories' => ClassifierValue::getActiveTypeNames()->values()->all(),
            'institutions' => $this->getActiveInstitutions(),
            'publishRules' => $this->getPublishRules(),
            'resolutionTermRules' => $this->getResolutionTermRules(),
            'classifierTypesWithNatures' => $this->getClassifierTypesWithNatures(),
            'statusOptions' => ProblemStatus::options(),
            'inactiveReporterSetting' => $this->getInactiveReporterSettingPayload(),
            'inactiveReportersList' => $this->getInactiveReportersList(),
            'resolvedProblemPrivacySetting' => $this->getResolvedProblemPrivacySettingPayload(),
            'resolvedPublicProblemsList' => $this->getResolvedPublicProblemsList(),
        ];
    }

    private function getAssignmentRules(): array
    {
        return ProblemAutoAssignmentRule::query()
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
            ])
            ->all();
    }

    private function getActiveInstitutions(): array
    {
        return Institution::query()
            ->notArchived()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    private function getPublishRules(): array
    {
        return ProblemAutoPublishRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn (ProblemAutoPublishRule $r) => [
                'id' => $r->id,
                'category' => $r->category,
                'status' => $r->status,
                'is_active' => $r->is_active,
            ])
            ->all();
    }

    private function getResolutionTermRules(): array
    {
        return ProblemResolutionTermRule::query()
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
            ])
            ->all();
    }

    private function getClassifierTypesWithNatures(): array
    {
        return ClassifierValue::notArchived()
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
    }

    private function getInactiveReporterSettingPayload(): array
    {
        $setting = InactiveReporterDeletionSetting::firstOrCreateDefault();

        return [
            'id' => $setting->id,
            'inactivity_days' => $setting->inactivity_days,
            'reminder_days' => $setting->reminderDays(),
            'is_active' => $setting->is_active,
            'inactivity_days_min' => InactiveReporterDeletionSetting::INACTIVITY_DAYS_MIN,
            'inactivity_days_max' => InactiveReporterDeletionSetting::INACTIVITY_DAYS_MAX,
            'inactivity_days_default' => InactiveReporterDeletionSetting::INACTIVITY_DAYS_DEFAULT,
        ];
    }

    private function getResolvedProblemPrivacySettingPayload(): array
    {
        $setting = ResolvedProblemPrivacySetting::firstOrCreateDefault();

        return [
            'id' => $setting->id,
            'days_after_resolution' => $setting->days_after_resolution,
            'is_active' => $setting->is_active,
            'days_min' => ResolvedProblemPrivacySetting::DAYS_AFTER_RESOLUTION_MIN,
            'days_max' => ResolvedProblemPrivacySetting::DAYS_AFTER_RESOLUTION_MAX,
            'days_default' => ResolvedProblemPrivacySetting::DAYS_AFTER_RESOLUTION_DEFAULT,
        ];
    }

    public function getInactiveReportersList(): array
    {
        $users = User::query()
            ->notArchived()
            ->whereDoesntHave('roles.permissions', fn ($q) => $q->where('permission', 'issues.view'))
            ->get(['id', 'name', 'surname', 'email', 'last_login_at', 'created_at']);

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
            $referenceAt = $lastLoginAt ?? $user->created_at;
            $inactiveDays = 0;
            if ($referenceAt !== null) {
                if ($referenceAt->greaterThan(now())) {
                    $inactiveDays = 0;
                } else {
                    $inactiveDays = (int) $referenceAt->copy()->startOfDay()->diffInDays(
                        now()->copy()->startOfDay(),
                        true
                    );
                }
            }

            $list[] = [
                'id' => $user->id,
                'name' => trim($user->name.' '.($user->surname ?? '')) ?: $user->email,
                'email' => $user->email,
                'last_login_at' => $lastLoginAt?->format('Y-m-d H:i'),
                'inactive_days' => $inactiveDays,
            ];
        }

        usort($list, fn ($a, $b) => ($b['inactive_days'] ?? -1) <=> ($a['inactive_days'] ?? -1));

        return $list;
    }

    public function getResolvedPublicProblemsList(): array
    {
        $problems = Problem::query()
            ->resolvedPublicRoot()
            ->orderByDesc('status_updated_at')
            ->get(['id', 'internal_number', 'address', 'description', 'status_updated_at']);

        $list = [];
        foreach ($problems as $problem) {
            $resolvedAt = $problem->status_updated_at;

            $list[] = [
                'id' => $problem->id,
                'internal_number' => $problem->internal_number,
                'address' => $problem->address,
                'description_preview' => Problem::descriptionPreview(
                    $problem->description,
                    Problem::AUTOMATION_RESOLVED_SEARCH_PREVIEW
                ),
                'problem_line' => $problem->automationResolvedListingLine(),
                'resolved_at' => $resolvedAt?->format('Y-m-d H:i'),
                'resolved_at_ts' => $resolvedAt?->timestamp,
            ];
        }

        usort($list, function ($a, $b) {
            $ta = $a['resolved_at_ts'] ?? 0;
            $tb = $b['resolved_at_ts'] ?? 0;

            return $ta <=> $tb;
        });

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
