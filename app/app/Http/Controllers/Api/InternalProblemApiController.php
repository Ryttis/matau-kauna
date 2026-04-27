<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProblemStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\FileUploadSettings;
use App\Models\Institution;
use App\Models\InstitutionTeam;
use App\Models\Problem;
use App\Models\ProblemAttachment;
use App\Models\User;
use App\Services\ProblemAssignmentNotifier;
use App\Services\ProblemDashboardQueryService;
use App\Services\ProblemHttpSupport;
use App\Services\ProblemInstitutionAssignmentPickerService;
use App\Services\ProblemResolutionTermService;
use App\Services\ProblemScopeService;
use App\Services\ReporterFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InternalProblemApiController extends Controller
{
    public function __construct(
        private ProblemScopeService $scopeService,
        private ProblemAssignmentNotifier $assignmentNotifier,
        private ProblemResolutionTermService $resolutionTermService,
        private ProblemDashboardQueryService $dashboardQuery,
        private ProblemHttpSupport $problemHttpSupport,
        private ReporterFileUploadService $reporterFileUploadService,
        private ProblemInstitutionAssignmentPickerService $institutionAssignmentPickerService,
    ) {}

    public function institutionsForAssign(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }

        $rows = Institution::query()
            ->notArchived()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json([
            'ok' => true,
            'institutions' => $rows->map(fn (Institution $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'code' => $i->code,
            ])->values()->all(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isReporterOnly()) {
            abort(403);
        }
        if (! $user->hasPermission('issues.view')) {
            abort(403);
        }

        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'category' => $request->query('category'),
            'report_number' => $request->query('report_number'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];
        $sortBy = (string) $request->query('sort_by', 'created_at');
        $sortDirection = (string) $request->query('sort_direction', 'desc');
        $perPage = (int) $request->query('per_page', 20);

        $paginator = $this->dashboardQuery->paginatedStaffProblems($user, $filters, $sortBy, $sortDirection, $perPage);

        return response()->json($paginator);
    }

    public function update(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        if (! $user->canEditProblemClassification()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $validated = $request->validate([
            'internal_number' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $problem->update($validated);

        $normalizedFiles = $this->problemHttpSupport->uploadedFilesForStorage($request);
        Validator::make(
            ['files' => $normalizedFiles],
            [
                'files' => 'nullable|array',
                'files.*' => 'file',
            ],
            [
                'files.array' => 'Netinkami priedai.',
                'files.*.file' => 'Failas per didelis arba nepriimtas.',
                'files.*.uploaded' => 'Nepavyko įkelti failo. Patikrinkite failo dydį ir bandykite dar kartą.',
            ]
        )->validate();

        $createdAttachments = [];
        if ($normalizedFiles !== []) {
            $channel = FileUploadSettings::resolveUploadChannel($request->header('X-Upload-Channel'));
            $createdAttachments = $this->reporterFileUploadService->validateAndStore(
                $problem,
                $normalizedFiles,
                $channel,
                true,
                false
            );
        }

        return response()->json([
            'ok' => true,
            'message' => 'Problema atnaujinta.',
            'problem_id' => $problem->id,
            'attachments' => collect($createdAttachments)
                ->map(fn (ProblemAttachment $attachment) => $attachment->toPublicPayload())
                ->values()
                ->all(),
        ]);
    }

    public function destroy(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        if (! $user->canDeleteProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $ids = $problem->getMergeGroupIds();
        Problem::whereIn('id', $ids)->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Problema perkelta į archyvą.',
            'problem_ids' => $ids,
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->canDeleteProblems()) {
            abort(403);
        }

        $validated = $request->validate([
            'problem_ids' => 'required|array|min:1',
            'problem_ids.*' => 'required|integer|exists:problems,id',
        ]);

        $problemIds = array_values(array_unique(array_map('intval', $validated['problem_ids'])));
        $query = Problem::query()->whereIn('id', $problemIds);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($problemIds));

        $allIds = Problem::expandIdsToMergeGroups($problemIds);
        Problem::query()->whereIn('id', $allIds)->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Problemos perkeltos į archyvą.',
            'problem_ids' => $allIds,
        ]);
    }

    public function updateStatus(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', ProblemStatus::valuesEligibleForManualStaffUpdate()),
        ]);

        $ids = $problem->getMergeGroupIds();
        $query = Problem::query()->whereIn('id', $ids);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($ids));

        $query->update([
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Būsena atnaujinta.',
            'problem_ids' => $ids,
            'status' => $validated['status'],
        ]);
    }

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'problem_ids' => 'required|array|min:1',
            'problem_ids.*' => 'required|integer|exists:problems,id',
            'status' => 'required|string|in:'.implode(',', ProblemStatus::valuesEligibleForManualStaffUpdate()),
        ]);

        $problemIds = array_values(array_unique(array_map('intval', $validated['problem_ids'])));
        $expandedIds = Problem::expandIdsToMergeGroups($problemIds);

        $query = Problem::query()->whereIn('id', $expandedIds);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($expandedIds));
        $query->update([
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Būsenos atnaujintos.',
            'problem_ids' => $expandedIds,
            'status' => $validated['status'],
        ]);
    }

    public function updatePublish(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        if (! $user->canPublishProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $validated = $request->validate([
            'is_published_publicly' => 'required|boolean',
        ]);

        $ids = $problem->getMergeGroupIds();
        $query = Problem::query()->whereIn('id', $ids);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($ids));
        $query->update(['is_published_publicly' => $validated['is_published_publicly']]);

        return response()->json([
            'ok' => true,
            'message' => $validated['is_published_publicly'] ? 'Paskelbta viešai.' : 'Nuimta nuo viešo skelbimo.',
            'problem_ids' => $ids,
            'is_published_publicly' => (bool) $validated['is_published_publicly'],
        ]);
    }

    public function bulkUpdatePublish(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->canPublishProblems()) {
            abort(403);
        }

        $validated = $request->validate([
            'problem_ids' => 'required|array|min:1',
            'problem_ids.*' => 'required|integer|exists:problems,id',
            'is_published_publicly' => 'required|boolean',
        ]);

        $problemIds = array_values(array_unique(array_map('intval', $validated['problem_ids'])));
        $expandedIds = Problem::expandIdsToMergeGroups($problemIds);
        $query = Problem::query()->whereIn('id', $expandedIds);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($expandedIds));
        $query->update(['is_published_publicly' => (bool) $validated['is_published_publicly']]);

        return response()->json([
            'ok' => true,
            'message' => $validated['is_published_publicly'] ? 'Problemos paskelbtos viešai.' : 'Problemos nuimtos nuo viešo skelbimo.',
            'problem_ids' => $expandedIds,
            'is_published_publicly' => (bool) $validated['is_published_publicly'],
        ]);
    }

    public function merge(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        $this->scopeService->authorizeMergeAccess($user, $problem);
        if ($problem->is_archived) {
            return response()->json(['message' => 'Archyvuotos problemos apjungti negalima.'], 422);
        }

        $validated = $request->validate([
            'problem_ids' => 'required|array|min:1',
            'problem_ids.*' => 'required|integer|exists:problems,id',
        ]);

        $ids = array_values(array_filter(
            array_unique(array_map('intval', $validated['problem_ids'])),
            fn (int $id) => $id !== (int) $problem->id
        ));

        $idsToMerge = [];
        $collectDescendants = function (int $problemId) use (&$collectDescendants, &$idsToMerge): void {
            $idsToMerge[$problemId] = true;
            $sub = Problem::query()->where('id', $problemId)->with('mergedProblems')->first();
            if (! $sub) {
                return;
            }
            foreach ($sub->mergedProblems as $m) {
                $collectDescendants((int) $m->id);
            }
        };
        foreach ($ids as $id) {
            $collectDescendants($id);
        }
        $idsToMerge = array_keys($idsToMerge);

        if (count($idsToMerge) === 0) {
            return response()->json(['ok' => true, 'message' => 'Nėra ką apjungti.', 'problem_ids' => []]);
        }

        if (Problem::query()->whereIn('id', $idsToMerge)->where('is_archived', true)->exists()) {
            return response()->json(['message' => 'Negalima apjungti archyvuotų problemų.'], 422);
        }

        $query = Problem::query()->whereIn('id', $idsToMerge);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($idsToMerge));
        $query->update(['merged_into_problem_id' => $problem->id]);

        return response()->json([
            'ok' => true,
            'message' => 'Problemos apjungtos.',
            'root_problem_id' => $problem->id,
            'merged_problem_ids' => $idsToMerge,
        ]);
    }

    public function unmerge(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        $this->scopeService->authorizeMergeAccess($user, $problem);
        if ($problem->merged_into_problem_id === null) {
            return response()->json(['ok' => true, 'message' => 'Problema neapjungta.', 'problem_id' => $problem->id]);
        }

        $problem->update(['merged_into_problem_id' => null]);

        return response()->json(['ok' => true, 'message' => 'Problema atskirta.', 'problem_id' => $problem->id]);
    }

    public function assignBulk(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }

        $statusesAllowedOnAssign = implode(',', array_values(array_filter(
            ProblemStatus::values(),
            fn (string $status) => $status !== ProblemStatus::RESOLVED->value
        )));

        $validated = $request->validate([
            'problem_ids' => ['required', 'array', 'min:1'],
            'problem_ids.*' => ['required', 'integer', 'exists:problems,id'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'status' => ['nullable', 'string', 'in:'.$statusesAllowedOnAssign],
        ]);

        $problemsToUpdate = Problem::query()->whereIn('id', $validated['problem_ids'])->get();
        if ($problemsToUpdate->contains(fn (Problem $p) => $p->status === ProblemStatus::RESOLVED->value)) {
            return response()->json(['message' => 'Išspręstų problemų negalima priskirti.'], 422);
        }

        $newInstitutionId = $validated['institution_id'] ?? null;
        $newStatus = $validated['status'] ?? null;

        foreach ($problemsToUpdate as $problem) {
            $attrs = ['institution_id' => $newInstitutionId];
            if ($newInstitutionId === null) {
                $attrs['assigned_employee_id'] = null;
                $attrs['assigned_institution_team_id'] = null;
            } elseif ((int) ($problem->institution_id ?? 0) !== (int) $newInstitutionId) {
                $attrs['assigned_employee_id'] = null;
                $attrs['assigned_institution_team_id'] = null;
            }
            if (! empty($newStatus)) {
                $attrs['status'] = $newStatus;
                $attrs['status_updated_at'] = now();
            }
            $problem->update($attrs);
        }

        if (! empty($newInstitutionId)) {
            foreach (Problem::query()->whereIn('id', $validated['problem_ids'])->get() as $p) {
                $this->resolutionTermService->applyRules($p);
            }
            $this->assignmentNotifier->notifyInstitutionOfAssignment($validated['problem_ids'], (int) $newInstitutionId);
        }

        return response()->json([
            'ok' => true,
            'message' => empty($newInstitutionId) ? 'Problemos atšauktos nuo įstaigos.' : 'Problemos priskirtos įstaigai.',
            'problem_ids' => array_values(array_map('intval', $validated['problem_ids'])),
        ]);
    }

    public function assignInstitutionOptions(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        if (! $user->canAssignInstitutionProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return response()->json(['message' => 'Išspręstos problemos priskirti negalima.'], 422);
        }

        $picker = $this->institutionAssignmentPickerService->pickerDataFor($user);

        return response()->json([
            'ok' => true,
            'problem_id' => $problem->id,
            'employees' => $picker['employees'],
            'teams' => $picker['teams'],
            'institutions' => $picker['institutions'],
        ]);
    }

    public function assignInstitution(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        if (! $user->canAssignInstitutionProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return response()->json(['message' => 'Išspręstos problemos priskirti negalima.'], 422);
        }

        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'institution_team_id' => ['nullable', 'integer', 'exists:institution_teams,id'],
            'completion_deadline' => ['nullable', 'date'],
        ]);

        $employeeId = $validated['employee_id'] ?? null;
        $teamId = $validated['institution_team_id'] ?? null;
        if (($employeeId === null) === ($teamId === null)) {
            return response()->json(['message' => 'Pasirinkite darbuotoją arba komandą.'], 422);
        }

        $deadline = array_key_exists('completion_deadline', $validated)
            ? ($validated['completion_deadline'] ?: null)
            : null;

        if ($employeeId !== null) {
            $assignableEmails = User::queryAssignableInstitutionEmployeeUsers(
                (int) $user->institution_id,
                $user->email,
            )->pluck('email');
            if ($assignableEmails->isEmpty()) {
                abort(403);
            }
            $employee = Employee::query()
                ->where('id', $employeeId)
                ->where('institution_id', $user->institution_id)
                ->whereIn('email', $assignableEmails)
                ->first();
            if (! $employee || $employee->email === $user->email) {
                abort(403);
            }

            $problem->update([
                'assigned_employee_id' => $employee->id,
                'assigned_institution_team_id' => null,
                'completion_deadline' => $deadline,
            ]);

            $this->assignmentNotifier->notifyEmployeeOfAssignment($problem->fresh(), $employee);

            return response()->json(['ok' => true, 'message' => 'Problema priskirta darbuotojui.', 'problem_id' => $problem->id]);
        }

        $team = InstitutionTeam::query()
            ->where('id', $teamId)
            ->where('institution_id', $user->institution_id)
            ->with('employees')
            ->first();
        if (! $team) {
            abort(403);
        }
        $assignableEmails = User::queryAssignableInstitutionEmployeeUsers(
            (int) $user->institution_id,
            $user->email,
        )->pluck('email');
        $teamAssignees = $assignableEmails->isEmpty()
            ? collect()
            : $team->employees->whereIn('email', $assignableEmails);
        if ($teamAssignees->isEmpty()) {
            return response()->json(['message' => 'Komanda neturi priskirtinų darbuotojų narių.'], 422);
        }

        $problem->update([
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => $team->id,
            'completion_deadline' => $deadline,
        ]);

        foreach ($teamAssignees as $employee) {
            if ($employee->email !== $user->email) {
                $this->assignmentNotifier->notifyEmployeeOfAssignment($problem->fresh(), $employee);
            }
        }

        return response()->json(['ok' => true, 'message' => 'Problema priskirta komandai.', 'problem_id' => $problem->id]);
    }

    public function reassignInstitution(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        $canReassign = $user->canReassignInstitutions()
            || ($this->scopeService->isInstitutionScoped($user)
                && $user->institution_id !== null
                && (int) $problem->institution_id === (int) $user->institution_id);
        if (! $canReassign) {
            abort(403);
        }

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return response()->json(['message' => 'Išspręstos problemos perduoti negalima.'], 422);
        }

        $validated = $request->validate([
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
        ]);

        if ((int) $problem->institution_id === (int) $validated['institution_id']) {
            return response()->json(['ok' => true, 'message' => 'Problema jau priskirta šiai įstaigai.', 'problem_id' => $problem->id]);
        }

        $problem->update([
            'institution_id' => $validated['institution_id'],
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => null,
        ]);

        $this->assignmentNotifier->notifyInstitutionOfAssignment([$problem->id], (int) $validated['institution_id']);

        return response()->json(['ok' => true, 'message' => 'Problema perduota kitai įstaigai.', 'problem_id' => $problem->id]);
    }

    public function returnToAdmin(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }
        if ($user->canReassignInstitutions()) {
            abort(403);
        }
        if ($this->scopeService->isInstitutionScoped($user) && $user->institution_id !== null) {
            if ((int) $problem->institution_id !== (int) $user->institution_id) {
                abort(403);
            }
        } elseif ($this->scopeService->isEmployeeScoped($user)) {
            $employeeId = $this->scopeService->employeeIdForUser($user);
            if (! $this->scopeService->employeeHasAccessToAssignment($employeeId, $problem)) {
                abort(403);
            }
        } else {
            abort(403);
        }

        $problem->update([
            'institution_id' => null,
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => null,
        ]);

        return response()->json(['ok' => true, 'message' => 'Problema grąžinta administratoriui.', 'problem_id' => $problem->id]);
    }
}
