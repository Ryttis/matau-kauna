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
use App\Services\ProblemAutoAssignmentService;
use App\Services\ProblemAutoPublishService;
use App\Services\ProblemDashboardQueryService;
use App\Services\ProblemHttpSupport;
use App\Services\ProblemInstitutionAssignmentPickerService;
use App\Services\ProblemMergeGroupSyncService;
use App\Services\ProblemReporterStatusNotifier;
use App\Services\ProblemResolutionTermService;
use App\Services\ProblemReviewHistoryService;
use App\Services\ProblemScopeService;
use App\Services\ReporterFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        private ProblemReviewHistoryService $reviewHistoryService,
        private ProblemReporterStatusNotifier $reporterStatusNotifier,
        private ProblemAutoAssignmentService $autoAssignmentService,
        private ProblemAutoPublishService $autoPublishService,
        private ProblemMergeGroupSyncService $mergeGroupSyncService,
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
            ->get(['id', 'name', 'code', 'contact_email', 'integration_email']);

        return response()->json([
            'ok' => true,
            'institutions' => $rows->map(fn (Institution $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'code' => $i->code,
                'emails' => array_values(array_unique(array_filter([
                    $i->contact_email,
                    $i->integration_email,
                ]))),
            ])->values()->all(),
        ]);
    }

    public function assignSendEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }

        $request->merge([
            'email' => trim((string) $request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'problem_ids' => ['required', 'array', 'min:1'],
            'problem_ids.*' => ['required', 'integer', 'exists:problems,id'],
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
        ]);

        $institution = Institution::query()->findOrFail($validated['institution_id']);
        $expandedIds = Problem::expandIdsToMergeGroups(array_map('intval', $validated['problem_ids']));

        $problems = Problem::whereIn('id', $expandedIds)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($problems->contains(fn (Problem $p) => $p->status === ProblemStatus::RESOLVED->value)) {
            return response()->json([
                'message' => 'Išnagrinėtų problemų negalima priskirti.',
            ], 422);
        }

        $loggedInstitutionRootIds = [];
        foreach ($problems as $p) {
            $rootId = (int) ($p->merged_into_problem_id ?? $p->id);
            if (isset($loggedInstitutionRootIds[$rootId])) {
                continue;
            }
            if ((int) ($p->institution_id ?? 0) === (int) $institution->id) {
                continue;
            }
            $loggedInstitutionRootIds[$rootId] = true;
            $this->reviewHistoryService->logInternalEventEntry(
                $p,
                $user,
                ProblemReviewHistoryService::institutionChangeNote(
                    Institution::query()->whereKey($p->institution_id)->value('name'),
                    $institution->name,
                ),
            );
        }

        Problem::whereIn('id', $expandedIds)->update([
            'institution_id' => $institution->id,
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => null,
        ]);

        $problems = Problem::query()
            ->whereIn('id', $expandedIds)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($problems as $p) {
            $this->resolutionTermService->applyRules($p);
        }

        $problems = Problem::query()
            ->whereIn('id', $expandedIds)
            ->orderBy('created_at', 'desc')
            ->get();

        $mail = $this->assignmentNotifier->buildManualAssignToInstitutionEmail($institution, $problems);
        if ($mail !== null) {
            \App\Services\TransactionalMail::send(
                $validated['email'],
                $mail['subject'],
                $mail['body'],
                ! empty($mail['action_url']) ? $mail['action_url'] : null,
                $mail['action_label'] ?? null,
            );
        }

        $rootIdsMap = Problem::rootIdsMapFromProblemIds($expandedIds);
        $oldByMain = Problem::statusesByRootId($rootIdsMap);
        Problem::whereIn('id', $expandedIds)->update([
            'status' => ProblemStatus::RESOLVED->value,
            'status_updated_at' => now(),
            'deadline_status' => null,
            'deadline_reminder_sent_at' => null,
        ]);
        $this->reporterStatusNotifier->notifyRootsAfterStatusChange($oldByMain, ProblemStatus::RESOLVED->value);
        foreach (array_keys($rootIdsMap) as $rootId) {
            $rootProblem = Problem::find($rootId);
            if ($rootProblem) {
                $this->reviewHistoryService->logStatusChangeEntry($rootProblem, $user, ProblemStatus::RESOLVED->value);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => $mail !== null ? 'Laiškas išsiųstas.' : 'Problema priskirta įstaigai.',
            'problem_ids' => $expandedIds,
            'status' => ProblemStatus::RESOLVED->value,
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
            'problem_nature_id' => $request->query('problem_nature_id'),
            'report_number' => $request->query('report_number'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'institution_id' => $request->query('institution_id'),
        ];
        $sortBy = (string) $request->query('sort_by', 'created_at');
        $sortDirection = (string) $request->query('sort_direction', 'desc');
        $perPage = (int) $request->query('per_page', 20);

        $paginator = $this->dashboardQuery->paginatedStaffProblems($user, $filters, $sortBy, $sortDirection, $perPage);

        $paginator->through(function (Problem $problem) {
            return $problem->toArray() + [
                'nature' => $problem->problemNature?->name,
                'institution_name' => $problem->institution?->name,
                'assigned_employee' => $problem->assignedEmployee,
                'assigned_institution_team' => $problem->assignedInstitutionTeam,
            ];
        });

        return response()->json($paginator);
    }

    public function show(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        if ($user->isReporterOnly() || ! $user->hasPermission('issues.view')) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $problem->load(['attachments', 'problemNature', 'institution']);

        return response()->json([
            'id' => $problem->id,
            'internal_number' => $problem->internal_number,
            'status' => $problem->status,
            'category' => $problem->category,
            'nature' => $problem->problemNature?->name,
            'institution_id' => $problem->institution_id,
            'institution_name' => $problem->institution?->name,
            'address' => $problem->address,
            'description' => $problem->description,
            'lat' => $problem->lat,
            'lng' => $problem->lng,
            'created_at' => $problem->created_at?->toIso8601String(),
            'is_published_publicly' => (bool) $problem->is_published_publicly,
            'attachments' => $this->problemHttpSupport->attachmentsPayload($problem),
        ]);
    }

    public function update(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        if (! $user->canEditProblemClassification()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $validated = $request->validate([
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

        $problem->refresh();
        $this->autoAssignmentService->applyRules($problem);
        $this->autoPublishService->applyRules($problem);

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
            'deadline_status' => null,
            'deadline_reminder_sent_at' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Problema perkelta į archyvą.',
            'problem_ids' => $ids,
        ]);
    }

    public function storeAttachments(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        if (! $user->canEditProblemClassification()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $normalizedFiles = $this->problemHttpSupport->uploadedFilesForStorage($request);

        Validator::make(
            ['files' => $normalizedFiles],
            [
                'files' => 'required|array|min:1',
                'files.*' => 'file',
            ],
            [
                'files.required' => 'Pasirinkite failą.',
                'files.min' => 'Pasirinkite failą.',
                'files.*.file' => 'Failas per didelis arba nepriimtas.',
                'files.*.uploaded' => 'Nepavyko įkelti failo. Patikrinkite failo dydį ir bandykite dar kartą.',
            ]
        )->validate();

        $channel = FileUploadSettings::resolveUploadChannel($request->header('X-Upload-Channel'));

        $created = $this->reporterFileUploadService->validateAndStore(
            $problem,
            $normalizedFiles,
            $channel,
            true,
            false
        );

        return response()->json([
            'ok' => true,
            'attachments' => collect($created)
                ->map(fn (ProblemAttachment $attachment) => $attachment->toPublicPayload())
                ->values()
                ->all(),
        ]);
    }

    public function destroyAttachment(Request $request, Problem $problem, ProblemAttachment $attachment): JsonResponse
    {
        $user = $request->user();
        if (! $user->canEditProblemClassification()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ((int) $attachment->problem_id !== (int) $problem->id) {
            abort(404);
        }

        $absolute = public_path(ltrim($attachment->path, '/'));
        if ($absolute !== '' && \Illuminate\Support\Facades\File::exists($absolute)) {
            \Illuminate\Support\Facades\File::delete($absolute);
        }

        $attachment->delete();

        return response()->json(['ok' => true]);
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
            'deadline_status' => null,
            'deadline_reminder_sent_at' => null,
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
        $mainId = $problem->merged_into_problem_id ?? $problem->id;
        $oldMainStatus = Problem::where('id', $mainId)->value('status');
        $query = Problem::query()->whereIn('id', $ids);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($ids));
        $changedIds = (clone $query)
            ->where('status', '!=', $validated['status'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $statusUpdate = [
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ];
        if (in_array($validated['status'], Problem::NON_NOTIFIABLE_STATUSES, true)) {
            $statusUpdate['deadline_status'] = null;
            $statusUpdate['deadline_reminder_sent_at'] = null;
        }
        if ($changedIds !== []) {
            Problem::whereIn('id', $changedIds)->update($statusUpdate);
        }

        $main = Problem::find($mainId);

        if ($changedIds !== [] && $oldMainStatus !== null && $oldMainStatus !== $validated['status'] && $main) {
            $this->reporterStatusNotifier->notifyReportersAndWatchersWhenStatusChanges($main, $oldMainStatus, $validated['status']);
        }

        if ($changedIds !== [] && $oldMainStatus !== null && $oldMainStatus !== $validated['status'] && $main) {
            $this->reviewHistoryService->logStatusChangeEntry($main, $user, $validated['status']);
        }

        foreach (Problem::query()->whereIn('id', $changedIds)->get() as $p) {
            $this->autoAssignmentService->applyRules($p);
            $this->autoPublishService->applyRules($p);
        }

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

        $rootIdsMap = Problem::rootIdsMapFromProblemIds($expandedIds);
        $oldByMain = Problem::statusesByRootId($rootIdsMap);

        $statusUpdate = [
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ];
        if (in_array($validated['status'], Problem::NON_NOTIFIABLE_STATUSES, true)) {
            $statusUpdate['deadline_status'] = null;
            $statusUpdate['deadline_reminder_sent_at'] = null;
        }
        $changedIds = (clone $query)
            ->where('status', '!=', $validated['status'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($changedIds !== []) {
            Problem::whereIn('id', $changedIds)->update($statusUpdate);
        }

        $this->reporterStatusNotifier->notifyRootsAfterStatusChange($oldByMain, $validated['status']);

        foreach ($oldByMain as $rootId => $oldStatus) {
            if ($oldStatus === null || $oldStatus === $validated['status']) {
                continue;
            }
            $rootProblem = Problem::find($rootId);
            if ($rootProblem) {
                $this->reviewHistoryService->logStatusChangeEntry($rootProblem, $user, $validated['status']);
            }
        }

        foreach (Problem::query()->whereIn('id', $changedIds)->get() as $p) {
            $this->autoAssignmentService->applyRules($p);
            $this->autoPublishService->applyRules($p);
        }

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

        $selectedIds = array_values(array_filter(
            array_unique(array_map('intval', $validated['problem_ids'])),
            fn (int $id) => $id !== (int) $problem->id
        ));

        if ($selectedIds === []) {
            return response()->json(['ok' => true, 'message' => 'Nėra ką apjungti.', 'problem_ids' => []]);
        }

        $groupIds = $this->collectMergeGroupIds(array_merge([(int) $problem->id], $selectedIds));

        if (Problem::query()->whereIn('id', $groupIds)->where('is_archived', true)->exists()) {
            return response()->json(['message' => 'Negalima apjungti archyvuotų problemų.'], 422);
        }

        if (Problem::query()->whereIn('id', $groupIds)->where('status', ProblemStatus::RESOLVED->value)->exists()) {
            return response()->json(['message' => 'Negalima apjungti išnagrinėtų problemų.'], 422);
        }

        $scopeQuery = Problem::query()->whereIn('id', $groupIds);
        $this->scopeService->scopeProblemsForUser($scopeQuery, $user);
        $this->scopeService->ensureScopeMatchesCount($scopeQuery, count($groupIds));

        $rootId = $this->mergeGroupSyncService->resolveMergeRootId($problem, $selectedIds);

        DB::transaction(function () use ($groupIds, $rootId) {
            Problem::query()
                ->whereIn('id', $groupIds)
                ->where('id', '!=', $rootId)
                ->update(['merged_into_problem_id' => $rootId]);

            Problem::query()
                ->where('id', $rootId)
                ->whereNotNull('merged_into_problem_id')
                ->update(['merged_into_problem_id' => null]);

            $this->mergeGroupSyncService->syncGroupFromRoot(Problem::query()->findOrFail($rootId));
        });

        return response()->json([
            'ok' => true,
            'message' => 'Problemos apjungtos.',
            'root_problem_id' => $rootId,
            'merged_problem_ids' => array_values(array_filter($groupIds, fn (int $id) => $id !== $rootId)),
        ]);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function collectMergeGroupIds(array $ids): array
    {
        $collected = [];
        $collectDescendants = function (int $problemId) use (&$collectDescendants, &$collected): void {
            if (isset($collected[$problemId])) {
                return;
            }
            $collected[$problemId] = true;
            $childIds = Problem::query()
                ->where('merged_into_problem_id', $problemId)
                ->pluck('id');
            foreach ($childIds as $childId) {
                $collectDescendants((int) $childId);
            }
        };

        foreach ($ids as $id) {
            $collectDescendants((int) $id);
        }

        return array_keys($collected);
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

        $submittedIds = array_map('intval', $validated['problem_ids']);
        if (Problem::query()->whereIn('id', $submittedIds)->where('status', ProblemStatus::RESOLVED->value)->exists()) {
            return response()->json(['message' => 'Išnagrinėtų problemų negalima priskirti.'], 422);
        }

        // Įstaiga ir būsena taikomos visai sujungimo grupei, kad apjungtos problemos
        // paveldėtų sprendžiančią įstaigą (kitaip joms neveikia DVS/GIS perdavimas).
        $expandedIds = Problem::expandIdsToMergeGroups($submittedIds);
        $problemsToUpdate = Problem::query()->whereIn('id', $expandedIds)->get();

        $newInstitutionId = $validated['institution_id'] ?? null;
        $newStatus = $validated['status'] ?? null;
        $rootIdsMap = Problem::rootIdsMapFromProblemIds($expandedIds);
        $oldByMain = ! empty($newStatus)
            ? Problem::statusesByRootId($rootIdsMap)
            : [];

        $newInstitutionName = $newInstitutionId !== null
            ? Institution::query()->whereKey($newInstitutionId)->value('name')
            : null;

        $this->logInstitutionChangeForRoots($rootIdsMap, $user, $newInstitutionId, $newInstitutionName);

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

        if (! empty($newStatus)) {
            $this->reporterStatusNotifier->notifyRootsAfterStatusChange($oldByMain, $newStatus);

            foreach ($oldByMain as $rootId => $oldStatus) {
                if ($oldStatus === null || $oldStatus === $newStatus) {
                    continue;
                }
                $rootProblem = Problem::find($rootId);
                if ($rootProblem) {
                    $this->reviewHistoryService->logStatusChangeEntry($rootProblem, $user, $newStatus);
                }
            }
        }

        if (! empty($newInstitutionId)) {
            // Terminas skaičiuojamas šakninei problemai ir perduodamas grupei,
            // kad apjungtos problemos negautų skirtingų terminų.
            foreach (array_keys($rootIdsMap) as $rootId) {
                $rootProblem = Problem::find($rootId);
                if (! $rootProblem) {
                    continue;
                }
                $this->resolutionTermService->applyRules($rootProblem);
                $this->mergeGroupSyncService->syncGroupFromRoot($rootProblem);
            }
            $this->assignmentNotifier->notifyInstitutionOfAssignment($submittedIds, (int) $newInstitutionId);
        }

        if (! empty($newStatus)) {
            foreach (Problem::query()->whereIn('id', $expandedIds)->get() as $p) {
                $this->autoPublishService->applyRules($p);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => empty($newInstitutionId) ? 'Problemos atšauktos nuo įstaigos.' : 'Problemos priskirtos įstaigai.',
            'problem_ids' => $expandedIds,
        ]);
    }

    /**
     * Būsena ir atlikimo terminas galioja visai sujungimo grupei; konkretus
     * darbuotojas/komanda lieka priskirti tik tai problemai, kurioje priskirta.
     */
    private function applyAssignmentToMergeGroup(Problem $problem, ?string $deadline): void
    {
        Problem::query()->whereIn('id', $problem->getMergeGroupIds())->update([
            'completion_deadline' => $deadline,
            'status' => ProblemStatus::ASSIGNED->value,
            'status_updated_at' => now(),
        ]);

        $problem->refresh();
    }

    /**
     * @param  array<int, bool>  $rootIdsMap
     */
    private function logInstitutionChangeForRoots(
        array $rootIdsMap,
        User $user,
        ?int $newInstitutionId,
        ?string $newInstitutionName,
    ): void {
        foreach (array_keys($rootIdsMap) as $rootId) {
            $rootProblem = Problem::find($rootId);
            if (! $rootProblem) {
                continue;
            }
            if ((int) ($rootProblem->institution_id ?? 0) === (int) ($newInstitutionId ?? 0)) {
                continue;
            }

            $this->reviewHistoryService->logInternalEventEntry(
                $rootProblem,
                $user,
                ProblemReviewHistoryService::institutionChangeNote(
                    Institution::query()->whereKey($rootProblem->institution_id)->value('name'),
                    $newInstitutionName,
                ),
            );
        }
    }

    public function assignInstitutionOptions(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        if (! $user->canAssignInstitutionProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return response()->json(['message' => 'Išnagrinėtos problemos priskirti negalima.'], 422);
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

    public function institutionTeamMembers(Request $request, InstitutionTeam $team): JsonResponse
    {
        $user = $request->user();
        if (! $user->canAssignInstitutionProblems()) {
            abort(403);
        }
        if ((int) $team->institution_id !== (int) $user->institution_id) {
            abort(403);
        }

        $assignableEmails = User::queryAssignableInstitutionEmployeeUsers(
            (int) $user->institution_id,
            $user->assignableInstitutionExcludedEmail(),
        )->pluck('email');

        $members = $team->employees()
            ->when($assignableEmails->isEmpty(), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($assignableEmails->isNotEmpty(), fn ($q) => $q->whereIn('email', $assignableEmails))
            ->orderBy('name')
            ->orderBy('surname')
            ->get(['id', 'name', 'surname', 'email'])
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => trim(($employee->name ?? '').' '.($employee->surname ?? '')) ?: $employee->email,
                'email' => $employee->email,
            ])
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'members' => $members,
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
            return response()->json(['message' => 'Išnagrinėtos problemos priskirti negalima.'], 422);
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
                $user->assignableInstitutionExcludedEmail(),
            )->pluck('email');
            if ($assignableEmails->isEmpty()) {
                abort(403);
            }
            $employee = Employee::query()
                ->where('id', $employeeId)
                ->where('institution_id', $user->institution_id)
                ->whereIn('email', $assignableEmails)
                ->first();
            if (! $employee) {
                abort(403);
            }

            $problem->update([
                'assigned_employee_id' => $employee->id,
                'assigned_institution_team_id' => null,
            ]);
            $this->applyAssignmentToMergeGroup($problem, $deadline);

            $this->reviewHistoryService->logInternalEventEntry(
                $problem,
                $user,
                ProblemReviewHistoryService::employeeAssignmentNote(
                    trim((string) $employee->name.' '.(string) $employee->surname)
                ),
                ProblemStatus::ASSIGNED->value,
            );

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
            $user->assignableInstitutionExcludedEmail(),
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
        ]);
        $this->applyAssignmentToMergeGroup($problem, $deadline);

        $this->reviewHistoryService->logInternalEventEntry(
            $problem,
            $user,
            ProblemReviewHistoryService::teamAssignmentNote((string) $team->name),
            ProblemStatus::ASSIGNED->value,
        );

        foreach ($teamAssignees as $employee) {
            if ($employee->email !== $user->email) {
                $this->assignmentNotifier->notifyEmployeeOfAssignment($problem->fresh(), $employee);
            }
        }

        return response()->json(['ok' => true, 'message' => 'Problema priskirta komandai.', 'problem_id' => $problem->id]);
    }

    public function sendAssignInstitutionEmail(Request $request, Problem $problem): JsonResponse
    {
        $user = $request->user();
        if (! $user->canAssignInstitutionProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return response()->json(['message' => 'Išnagrinėtos problemos priskirti negalima.'], 422);
        }
        if ($user->institution_id === null) {
            return response()->json(['message' => 'Negalima siųsti el. laiško be įstaigos konteksto.'], 422);
        }

        $request->merge([
            'email' => trim((string) $request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $mail = $this->assignmentNotifier->buildInstitutionProblemShareEmail($problem);
        if ($mail !== null) {
            \App\Services\TransactionalMail::send(
                $validated['email'],
                $mail['subject'],
                $mail['body'],
                $mail['action_url'] ?? null,
                $mail['action_label'] ?? null,
            );
        }

        return response()->json([
            'ok' => true,
            'message' => $mail !== null
                ? 'Informacija išsiųsta el. paštu.'
                : 'El. laiškas išjungtas, todėl informacija nebuvo išsiųsta.',
            'problem_id' => $problem->id,
        ]);
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
            return response()->json(['message' => 'Išnagrinėtos problemos perduoti negalima.'], 422);
        }

        $validated = $request->validate([
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
        ]);

        if ((int) $problem->institution_id === (int) $validated['institution_id']) {
            return response()->json(['ok' => true, 'message' => 'Problema jau priskirta šiai įstaigai.', 'problem_id' => $problem->id]);
        }

        $this->reviewHistoryService->logInternalEventEntry(
            $problem,
            $user,
            ProblemReviewHistoryService::institutionChangeNote(
                Institution::query()->whereKey($problem->institution_id)->value('name'),
                Institution::query()->whereKey($validated['institution_id'])->value('name'),
            ),
        );

        Problem::query()->whereIn('id', $problem->getMergeGroupIds())->update([
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
        if (! $user->canReassignInstitutions()) {
            abort(403);
        }
        if ($user->institution_id === null) {
            abort(403);
        }
        if ((int) $problem->institution_id !== (int) $user->institution_id) {
            abort(403);
        }

        $problem->refresh();
        $this->reviewHistoryService->storeAutomatedInternalNote(
            $problem,
            $user,
            ProblemReviewHistoryService::INTERNAL_NOTE_RETURNED_TO_ADMIN,
        );

        Problem::query()->whereIn('id', $problem->getMergeGroupIds())->update([
            'institution_id' => null,
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => null,
            'status' => ProblemStatus::RETURNED_FOR_REVIEW->value,
            'status_updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Problema grąžinta administratoriui.',
            'problem_id' => $problem->id,
            'status' => ProblemStatus::RETURNED_FOR_REVIEW->value,
        ]);
    }
}
