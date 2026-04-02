<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Exports\ProblemsExport;
use App\Models\ClassifierValue;
use App\Models\FileUploadSettings;
use App\Models\Problem;
use App\Models\UserRole;
use App\Services\ProblemDashboardQueryService;
use App\Services\ProblemMergeContextService;
use App\Services\ProblemReporterStatusNotifier;
use App\Services\ProblemScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->hasRole(UserRole::ROLE_REPORTER) && ! $user->hasPermission('issues.view')) {
            return Inertia::render('Dashboard', $this->dashboardQuery->reporterDashboardProps(
                $user,
                $request->boolean('only_mine')
            ));
        }

        if (! $user->hasPermission('issues.view')) {
            abort(403);
        }

        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'category' => $request->get('category'),
            'report_number' => $request->get('report_number'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        return Inertia::render('Dashboard', $this->dashboardQuery->staffDashboardProps(
            $user,
            $filters,
            $sortBy,
            $sortDirection
        ));
    }

    public function updateStatus(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', ProblemStatus::values()),
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

        if ($oldMainStatus !== null && $oldMainStatus !== $validated['status']) {
            $main = Problem::find($mainId);
            if ($main) {
                $this->reporterStatusNotifier->notifyReportersAndWatchersWhenStatusChanges($main, $oldMainStatus, $validated['status']);
            }
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
            'status' => 'required|string|in:'.implode(',', ProblemStatus::values()),
        ]);

        $query = Problem::whereIn('id', $validated['problem_ids']);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($validated['problem_ids']));

        $rootIdsMap = Problem::rootIdsMapFromProblemIds($validated['problem_ids']);
        $oldByMain = Problem::statusesByRootId($rootIdsMap);

        $query->update([
            'status' => $validated['status'],
            'status_updated_at' => now(),
        ]);

        $this->reporterStatusNotifier->notifyRootsAfterStatusChange($oldByMain, $validated['status']);

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

        $query = Problem::whereIn('id', $validated['problem_ids']);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($validated['problem_ids']));
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
        $problem->load([
            'institution:id,name',
            'assignedEmployee:id,name,surname,email',
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
            'internal_number' => 'required|string|max:255',
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
        if (! $user->hasRole(UserRole::ROLE_EMPLOYEE) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $ids = $problem->getMergeGroupIds();
        Problem::whereIn('id', $ids)->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        return redirect()->route('dashboard')->with('success', 'Problema perkelta į archyvą');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_EMPLOYEE) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }

        $validated = $request->validate([
            'problem_ids' => 'required|array|min:1',
            'problem_ids.*' => 'required|integer|exists:problems,id',
        ]);

        $query = Problem::whereIn('id', $validated['problem_ids']);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($validated['problem_ids']));

        $allIds = Problem::expandIdsToMergeGroups($validated['problem_ids']);
        Problem::whereIn('id', $allIds)->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $message = count($validated['problem_ids']) > 1
            ? 'Problemos perkeltos į archyvą'
            : 'Problema perkelta į archyvą';

        return redirect()->back()->with('success', $message);
    }

    public function export(Request $request)
    {
        $user = $request->user();
        if (! $user->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }

        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'category' => $request->get('category'),
            'report_number' => $request->get('report_number'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
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
            (int) $request->get('page', 1)
        );

        $filename = 'problemos_'.date('Y-m-d_His').'.xlsx';

        return Excel::download(new ProblemsExport($problems), $filename);
    }
}
