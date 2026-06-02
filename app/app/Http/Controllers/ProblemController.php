<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Exports\ProblemsExport;
use App\Models\ClassifierValue;
use App\Models\FileUploadSettings;
use App\Models\Problem;
use App\Models\ProblemAttachment;
use App\Services\ProblemAutoAssignmentService;
use App\Services\ProblemAutoPublishService;
use App\Services\ProblemDashboardQueryService;
use App\Services\ProblemMergeContextService;
use App\Services\ProblemReporterStatusNotifier;
use App\Services\ProblemReviewHistoryService;
use App\Services\ProblemScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ProblemController extends Controller
{
    public function __construct(
        private ProblemMergeContextService $mergeContextService,
        private ProblemDashboardQueryService $dashboardQuery,
        private ProblemScopeService $scopeService,
        private ProblemReporterStatusNotifier $reporterStatusNotifier,
        private ProblemReviewHistoryService $reviewHistoryService,
        private ProblemAutoAssignmentService $autoAssignmentService,
        private ProblemAutoPublishService $autoPublishService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->isReporterOnly()) {
            return Inertia::render('Dashboard', $this->dashboardQuery->reporterDashboardProps(
                $user,
                $request->boolean('only_mine'),
                $request->boolean('only_watched'),
            ));
        }

        if (! $user->hasPermission('issues.view')) {
            abort(403);
        }

        $filters = [
            'search' => $request->get('search'),
            'statuses' => $this->requestedStatuses($request),
            'category' => $request->get('category'),
            'problem_nature_id' => $request->get('problem_nature_id'),
            'report_number' => $request->get('report_number'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'institution_id' => $request->get('institution_id'),
        ];

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $perPage = (int) $request->get('per_page', 10);

        return Inertia::render('Dashboard', $this->dashboardQuery->staffDashboardProps(
            $user,
            $filters,
            $sortBy,
            $sortDirection,
            $perPage
        ));
    }

    private function requestedStatuses(Request $request): array
    {
        $statuses = $request->input('statuses', []);
        if (! is_array($statuses)) {
            $statuses = [$statuses];
        }

        $legacyStatus = $request->input('status', []);
        if (! is_array($legacyStatus)) {
            $legacyStatus = filled($legacyStatus) ? [$legacyStatus] : [];
        }

        $statuses = array_merge($statuses, $legacyStatus);
        $valid = ProblemStatus::values();

        return array_values(array_filter(
            array_map('strval', $statuses),
            fn (string $status) => in_array($status, $valid, true),
        ));
    }

    public function updateStatus(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', ProblemStatus::valuesEligibleForManualStaffUpdate()),
        ]);

        $ids = $problem->getMergeGroupIds();
        $mainId = $problem->merged_into_problem_id ?? $problem->id;
        $oldMainStatus = Problem::where('id', $mainId)->value('status');
        $query = Problem::whereIn('id', $ids);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($ids));
        $query->update([
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ]);

        $main = Problem::find($mainId);

        if ($oldMainStatus !== null && $oldMainStatus !== $validated['status']) {
            if ($main) {
                $this->reporterStatusNotifier->notifyReportersAndWatchersWhenStatusChanges($main, $oldMainStatus, $validated['status']);
            }
        }

        if ($main) {
            $this->reviewHistoryService->logStatusChangeEntry($main, $user, $validated['status']);
        }

        foreach (Problem::query()->whereIn('id', $ids)->get() as $p) {
            $this->autoAssignmentService->applyRules($p);
            $this->autoPublishService->applyRules($p);
        }

        $message = count($ids) > 1
            ? 'Būsena atnaujinta visoms apjungtoms problemoms.'
            : 'Būsena atnaujinta';

        return redirect()->back()->with('success', $message);
    }

    public function bulkUpdateStatus(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'problem_ids' => 'required|array',
            'problem_ids.*' => 'required|integer|exists:problems,id',
            'status' => 'required|string|in:'.implode(',', ProblemStatus::valuesEligibleForManualStaffUpdate()),
        ]);

        $problemIds = array_values(array_unique(array_map('intval', $validated['problem_ids'])));
        $expandedIds = Problem::expandIdsToMergeGroups($problemIds);

        $query = Problem::whereIn('id', $expandedIds);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($expandedIds));

        $rootIdsMap = Problem::rootIdsMapFromProblemIds($expandedIds);
        $oldByMain = Problem::statusesByRootId($rootIdsMap);

        $query->update([
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ]);

        $this->reporterStatusNotifier->notifyRootsAfterStatusChange($oldByMain, $validated['status']);

        foreach (array_keys($rootIdsMap) as $rootId) {
            $rootProblem = Problem::find($rootId);
            if ($rootProblem) {
                $this->reviewHistoryService->logStatusChangeEntry($rootProblem, $user, $validated['status']);
            }
        }

        foreach (Problem::query()->whereIn('id', $expandedIds)->get() as $p) {
            $this->autoAssignmentService->applyRules($p);
            $this->autoPublishService->applyRules($p);
        }

        return redirect()->back()->with('success', 'Būsenos atnaujintos');
    }

    public function updatePublish(Request $request, Problem $problem): RedirectResponse
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
        $query = Problem::whereIn('id', $ids);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($ids));
        $query->update(['is_published_publicly' => $validated['is_published_publicly']]);

        $label = $validated['is_published_publicly'] ? 'Paskelbta viešai.' : 'Nuimta nuo viešo skelbimo.';

        return redirect()->back()->with('success', $label);
    }

    public function bulkUpdatePublish(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canPublishProblems()) {
            abort(403);
        }
        $validated = $request->validate([
            'problem_ids' => 'required|array',
            'problem_ids.*' => 'required|integer|exists:problems,id',
            'is_published_publicly' => 'required|boolean',
        ]);

        $problemIds = array_values(array_unique(array_map('intval', $validated['problem_ids'])));
        $expandedIds = Problem::expandIdsToMergeGroups($problemIds);

        $query = Problem::whereIn('id', $expandedIds);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($expandedIds));
        $query->update(['is_published_publicly' => $validated['is_published_publicly']]);

        $label = $validated['is_published_publicly'] ? 'Problemos paskelbtos viešai.' : 'Problemos nuimtos nuo viešo skelbimo.';

        return redirect()->back()->with('success', $label);
    }

    public function show(Problem $problem): Response
    {
        $user = request()->user();
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $categories = ClassifierValue::getActiveTypeNames()->values();

        $problem->load('mergedProblems:id,internal_number,merged_into_problem_id');
        $problem->load('mergedInto:id,internal_number');
        $problem->load('attachments');
        $problem->load('problemNature:id,name');
        $problem->load([
            'institution:id,name',
            'reportedByUser:id,name,surname,email,phone',
            'assignedEmployee:id,name,surname,email',
            'assignedInstitutionTeam:id,name',
        ]);

        $mergeContext = $this->mergeContextService->forProblem($problem);

        return Inertia::render('Problems/Show', [
            'problem' => $problem,
            'mergeTarget' => $mergeContext['mergeTarget'],
            'mergedChildren' => $mergeContext['mergedChildren'],
            'mergedSiblings' => $mergeContext['mergedSiblings'],
            'statusOptions' => ProblemStatus::selectableOptions(),
            'categories' => $categories,
            'fileUpload' => FileUploadSettings::getChannelForClient(FileUploadSettings::CHANNEL_WEB),
        ]);
    }

    public function update(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        $canEdit = $user->canEditProblemClassification();
        if (! $canEdit) {
            abort(403);
        }

        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $problem->update($validated);

        return redirect()->back()->with('success', 'Problema atnaujinta');
    }

    public function destroy(Problem $problem): RedirectResponse
    {
        $user = request()->user();
        if (! $user->canDeleteProblems()) {
            abort(403);
        }

        $ids = $problem->getMergeGroupIds();
        Problem::whereIn('id', $ids)->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        return redirect()->route('dashboard')->with('success', 'Problema perkelta į archyvą');
    }

    public function destroyAttachment(Request $request, Problem $problem, ProblemAttachment $attachment): RedirectResponse
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
        if ($absolute !== '' && File::exists($absolute)) {
            File::delete($absolute);
        }

        $attachment->delete();

        return redirect()->back()->with('success', 'Priedas pašalintas.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
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

        $query = Problem::whereIn('id', $problemIds);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($problemIds));

        $allIds = Problem::expandIdsToMergeGroups($problemIds);
        Problem::whereIn('id', $allIds)->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $message = count($problemIds) > 1
            ? 'Problemos perkeltos į archyvą'
            : 'Problema perkelta į archyvą';

        return redirect()->back()->with('success', $message);
    }

    public function export(Request $request)
    {
        $user = $request->user();
        if (! $user->hasPermission('issues.export')) {
            abort(403);
        }

        $filters = [
            'search' => $request->get('search'),
            'statuses' => $this->requestedStatuses($request),
            'category' => $request->get('category'),
            'problem_nature_id' => $request->get('problem_nature_id'),
            'report_number' => $request->get('report_number'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'institution_id' => $request->get('institution_id'),
        ];

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $scope = $request->get('scope', 'current_page');

        $problems = $this->dashboardQuery->problemsForExport(
            $user,
            $filters,
            $sortBy,
            $sortDirection,
            $scope,
            (int) $request->get('page', 1),
            (int) $request->get('per_page', 10)
        );

        $filename = 'problemos_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(new ProblemsExport($problems), $filename);
    }
}
