<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassifierValue;
use App\Models\DataRetentionRule;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    public function index(): JsonResponse
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

        return response()->json([
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
        ]);
    }

    public function updateRetentionRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['boolean'],
        ]);
        $days = isset($data['retention_days']) && $data['retention_days'] !== '' ? (int) $data['retention_days'] : null;
        $payload = [
            'entity_type' => DataRetentionRule::ENTITY_PROBLEM,
            'archive_after_days' => $days,
            'delete_after_days' => $days,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        $rule = DataRetentionRule::query()
            ->where('entity_type', DataRetentionRule::ENTITY_PROBLEM)
            ->first();

        if ($rule) {
            $rule->update($payload);
        } else {
            DataRetentionRule::create($payload);
        }

        return response()->json(['ok' => true]);
    }

    public function restoreUser(User $user): JsonResponse
    {
        if (! $user->archived_at) {
            return response()->json(['message' => 'Naudotojas nearchyvuotas.'], 422);
        }
        $user->update(['archived_at' => null]);
        return response()->json(['ok' => true]);
    }

    public function restoreInstitution(Institution $institution): JsonResponse
    {
        if (! $institution->archived_at) {
            return response()->json(['message' => 'Įstaiga nearchyvuota.'], 422);
        }
        $institution->update(['archived_at' => null]);
        return response()->json(['ok' => true]);
    }

    public function restoreProblem(Problem $problem): JsonResponse
    {
        if (! $problem->is_archived) {
            return response()->json(['message' => 'Problema nearchyvuota.'], 422);
        }
        $problem->update(['is_archived' => false, 'archived_at' => null]);
        return response()->json(['ok' => true]);
    }

    public function restoreClassifierValue(ClassifierValue $classifier_value): JsonResponse
    {
        if (! $classifier_value->archived_at) {
            return response()->json(['message' => 'Klasifikatorius nearchyvuotas.'], 422);
        }
        $classifier_value->update(['archived_at' => null]);
        if ($classifier_value->parent_id === null) {
            ClassifierValue::where('parent_id', $classifier_value->id)->update(['archived_at' => null]);
        }
        return response()->json(['ok' => true]);
    }
}
