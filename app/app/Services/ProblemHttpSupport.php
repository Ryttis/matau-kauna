<?php

namespace App\Services;

use App\Enums\ProblemStatus;
use App\Models\FileUploadSettings;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\ProblemAttachment;
use App\Models\ProblemWatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProblemHttpSupport
{
    public function __construct(
        private ProblemScopeService $scopeService,
        private ProblemReviewHistoryService $reviewHistoryService,
    ) {}

    public function institutionsForAssignInertia(): Collection
    {
        return Institution::query()
            ->notArchived()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'contact_email', 'integration_email'])
            ->map(fn (Institution $institution) => [
                'id' => $institution->id,
                'name' => $institution->name,
                'emails' => array_values(array_unique(array_filter([
                    $institution->contact_email,
                    $institution->integration_email,
                ]))),
            ]);
    }

    public function reviewProgressResponse(Problem $problem, bool $reporterBackToDashboard): Response
    {
        $user = request()->user();
        $reviewEntries = $this->reviewHistoryService->visibleEntriesForProblem(
            $problem,
            $user,
            $reporterBackToDashboard
        );

        $canAddEntry = ! $reporterBackToDashboard;

        $showWatchToggle = false;
        $isWatched = false;
        $watchProblemId = $problem->merged_into_problem_id ?? $problem->id;
        if ($user && $this->reporterCanToggleWatch($user, $problem)) {
            $showWatchToggle = true;
            $isWatched = $this->problemIsWatchedByUser($user, $problem);
        }

        $canDeleteReviewAttachments = $user && $canAddEntry
            ? $this->userCanDeleteReviewAttachment($user, $problem)
            : false;

        return Inertia::render('Problems/ReviewProgress', [
            'problem' => $problem,
            'reviewEntries' => $reviewEntries,
            'backUrl' => $reporterBackToDashboard ? route('dashboard') : route('problems.show', $problem->id),
            'canAddEntry' => $canAddEntry,
            'canDeleteReviewAttachments' => $canDeleteReviewAttachments,
            'showReviewVisibilityLabels' => $canAddEntry,
            'showWatchToggle' => $showWatchToggle,
            'isWatched' => $isWatched,
            'watchProblemId' => $watchProblemId,
            'fileUpload' => FileUploadSettings::getChannelForClient(FileUploadSettings::CHANNEL_WEB),
            'authUserId' => $user?->id,
            'isSystemAdmin' => $user ? $user->canAccessAdmin() : false,
        ]);
    }

    public function userCanDeleteReviewAttachment(User $user, Problem $problem): bool
    {
        if ($user->canAccessAdmin()) {
            return true;
        }

        $employeeId = $this->scopeService->employeeIdForUser($user);
        if (! $employeeId) {
            return false;
        }

        return $this->scopeService->employeeHasAccessToAssignment($employeeId, $problem);
    }

    public function reporterCanToggleWatch(User $user, Problem $problem): bool
    {
        if (! $user->isReporterOnly()) {
            return false;
        }

        $problem->loadMissing('mergedInto');
        $mainId = $problem->merged_into_problem_id ?? $problem->id;
        $main = Problem::query()->find($mainId);
        $groupIds = $problem->getMergeGroupIds();
        $ownsReport = Problem::query()
            ->whereIn('id', $groupIds)
            ->where('reported_by_user_id', $user->id)
            ->exists();

        return $ownsReport || ($main && $main->is_published_publicly);
    }

    public function problemIsWatchedByUser(User $user, Problem $problem): bool
    {
        $groupIds = $problem->getMergeGroupIds();

        return ProblemWatch::query()
            ->where('user_id', $user->id)
            ->whereIn('problem_id', $groupIds)
            ->exists();
    }

    public function reporterMergeRootIdsForFilters(User $user, array $filters): Collection
    {
        $fromMainFilter = Problem::query()
            ->forReporter($user->id)
            ->filtered($filters)
            ->pluck('id');

        $childMatchRootIds = collect(Problem::mergeGroupRootIdsFromMatchingMergedChildren($filters));

        $fromChildFilter = $childMatchRootIds->filter(
            fn ($id) => Problem::query()->forReporter($user->id)->whereKey((int) $id)->exists()
        );

        return $fromMainFilter->merge($fromChildFilter)->unique()->values();
    }

    public function reporterProblemsFlatOrderedForList(User $user, array $filters): Collection
    {
        $rootIds = $this->reporterMergeRootIdsForFilters($user, $filters);
        if ($rootIds->isEmpty()) {
            return collect();
        }

        $rootIdArr = $rootIds->map(fn ($id) => (int) $id)->all();

        $members = Problem::query()
            ->where(function ($query) use ($rootIdArr) {
                $query->whereIn('id', $rootIdArr)
                    ->orWhereIn('merged_into_problem_id', $rootIdArr);
            })
            ->notArchived()
            ->whereIn('status', ProblemStatus::reporterOwnedProblemsQueryStatusValues())
            ->get([
                'id',
                'internal_number',
                'status',
                'created_at',
                'category',
                'address',
                'description',
                'is_published_publicly',
                'merged_into_problem_id',
            ]);

        $rootsOrdered = Problem::query()
            ->whereIn('id', $rootIdArr)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'internal_number',
                'status',
                'created_at',
                'category',
                'address',
                'description',
                'is_published_publicly',
                'merged_into_problem_id',
            ]);

        $ordered = collect();
        foreach ($rootsOrdered as $main) {
            $ordered->push($main);
            foreach ($members->where('merged_into_problem_id', $main->id)->sortByDesc('created_at') as $child) {
                $ordered->push($child);
            }
        }

        return $ordered;
    }

    public function mapProblemToStatisticsListItem(Problem $problem): array
    {
        return [
            'id' => $problem->id,
            'internal_number' => $problem->internal_number,
            'created_at' => $problem->created_at?->toIso8601String(),
            'description_preview' => Problem::descriptionPreview($problem->description, 120),
        ];
    }

    public function buildReporterMonthlyReports(Collection $problems): array
    {
        $tz = config('app.timezone', 'UTC');
        $monthlyCounts = $problems->filter(fn ($problem) => $problem->created_at !== null)
            ->groupBy(fn ($problem) => $problem->created_at->timezone($tz)->format('Y-m'))
            ->map->count()
            ->all();

        $end = Carbon::now($tz)->endOfMonth();
        $startDefault = Carbon::now($tz)->subMonths(11)->startOfMonth();
        $start = $startDefault;

        if (! empty($monthlyCounts)) {
            $monthsWithData = array_keys($monthlyCounts);
            sort($monthsWithData);
            $earliestMonth = Carbon::parse($monthsWithData[0].'-01', $tz)->startOfMonth();
            if ($earliestMonth->lt($start)) {
                $start = $earliestMonth;
            }
        }

        $monthly = [];
        $maxMonths = 24;

        for ($m = $start->copy(); $m->lte($end) && count($monthly) < $maxMonths; $m->addMonth()) {
            $key = $m->format('Y-m');
            $monthly[$key] = $monthlyCounts[$key] ?? 0;
        }

        return array_map(
            fn ($month, $value) => ['month' => $month, 'value' => $value],
            array_keys($monthly),
            array_values($monthly)
        );
    }

    public function attachmentsPayload(Problem $problem): array
    {
        return $problem->attachments->map(fn (ProblemAttachment $attachment) => $attachment->toPublicPayload())->values()->all();
    }

    public function normalizedUploadedFiles(Request $request): array
    {
        $uploadedFiles = $request->file('files');
        if ($uploadedFiles === null) {
            return [];
        }

        return is_array($uploadedFiles) ? $uploadedFiles : [$uploadedFiles];
    }
}
