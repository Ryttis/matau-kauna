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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProblemHttpSupport
{
    private const REPORTER_LIST_COLUMNS = [
        'id',
        'internal_number',
        'status',
        'created_at',
        'category',
        'problem_nature_id',
        'address',
        'description',
        'is_published_publicly',
        'merged_into_problem_id',
    ];

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
            'fileUpload' => FileUploadSettings::getReviewProgressChannelForClient(),
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

    public function reporterMergeRootIdsForFilters(User $user, array $filters, bool $includeAllStatuses = false): Collection
    {
        $fromMainFilter = Problem::query()
            ->notArchived()
            ->whereNull('merged_into_problem_id')
            ->where(function ($q) use ($user) {
                $q->where('reported_by_user_id', $user->id)
                    ->orWhereHas('mergedProblems', fn ($q2) => $q2->where('reported_by_user_id', $user->id));
            })
            ->when(
                ! $includeAllStatuses,
                fn ($query) => $query->whereIn('status', ProblemStatus::reporterOwnedProblemsQueryStatusValues())
            )
            ->filtered($filters)
            ->pluck('id');

        $childMatchRootIds = collect(Problem::mergeGroupRootIdsFromMatchingMergedChildren($filters));

        $fromChildFilter = $childMatchRootIds->filter(fn ($id) => Problem::query()
            ->notArchived()
            ->whereNull('merged_into_problem_id')
            ->whereKey((int) $id)
            ->where(function ($q) use ($user) {
                $q->where('reported_by_user_id', $user->id)
                    ->orWhereHas('mergedProblems', fn ($q2) => $q2->where('reported_by_user_id', $user->id));
            })
            ->when(
                ! $includeAllStatuses,
                fn ($query) => $query->whereIn('status', ProblemStatus::reporterOwnedProblemsQueryStatusValues())
            )
            ->exists()
        );

        return $fromMainFilter->merge($fromChildFilter)->unique()->values();
    }

    public function reporterProblemsFlatOrderedForList(User $user, array $filters, bool $includeAllStatuses = false): Collection
    {
        $rootIds = $this->reporterMergeRootIdsForFilters($user, $filters, $includeAllStatuses);
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
            ->when(
                ! $includeAllStatuses,
                fn ($query) => $query->whereIn('status', ProblemStatus::reporterOwnedProblemsQueryStatusValues())
            )
            ->with(['problemNature:id,name'])
            ->get(self::REPORTER_LIST_COLUMNS);

        $rootsOrdered = Problem::query()
            ->whereIn('id', $rootIdArr)
            ->orderByDesc('created_at')
            ->with(['problemNature:id,name'])
            ->get(self::REPORTER_LIST_COLUMNS);

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
        $primary = $this->flattenUploadedFileInput(
            $request->file('files')
            ?? $request->file('files.*')
            ?? $request->file('files[]')
        );

        if ($primary !== []) {
            return $primary;
        }

        return $this->flattenUploadedFilesFromAllFiles($request);
    }

    public function uploadedFilesForStorage(Request $request): array
    {
        $multipart = $this->normalizedUploadedFiles($request);
        if ($multipart !== []) {
            return $multipart;
        }

        return $this->uploadedFilesFromJsonFilesField($request->input('files'));
    }

    private function uploadedFilesFromJsonFilesField(mixed $files): array
    {
        if ($files === null || $files === [] || $files === '') {
            return [];
        }

        if (! is_array($files)) {
            throw ValidationException::withMessages([
                'files' => ['Netinkami priedai.'],
            ]);
        }

        $out = [];
        foreach (array_values($files) as $i => $item) {
            if (is_string($item)) {
                $out[] = $this->base64ToUploadedFile($item, 'file_'.$i);

                continue;
            }

            if (is_array($item)) {
                $b64 = $item['content_base64'] ?? $item['data'] ?? null;
                if (! is_string($b64)) {
                    throw ValidationException::withMessages([
                        'files' => ['Netinkami priedai.'],
                    ]);
                }
                $name = $item['original_name'] ?? $item['name'] ?? 'file_'.$i;
                $name = is_string($name) ? basename($name) : 'file_'.$i;
                if ($name === '' || $name === '.' || $name === '..') {
                    $name = 'file_'.$i;
                }
                $out[] = $this->base64ToUploadedFile($b64, $name);

                continue;
            }

            throw ValidationException::withMessages([
                'files' => ['Netinkami priedai.'],
            ]);
        }

        return $out;
    }

    private function base64ToUploadedFile(string $base64, string $clientFilename): UploadedFile
    {
        $trimmed = preg_replace('#^data:[^;]+;base64,#', '', $base64) ?? $base64;
        $binary = base64_decode($trimmed, true);
        if ($binary === false) {
            throw ValidationException::withMessages([
                'files' => ['Netinkamas base64 kodavimas.'],
            ]);
        }

        $maxBytes = (int) min(40 * 1024 * 1024, UploadedFile::getMaxFilesize() ?: 12 * 1024 * 1024);
        if (strlen($binary) > $maxBytes) {
            throw ValidationException::withMessages([
                'files' => ['Failas per didelis.'],
            ]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'jsonup');
        if ($tmp === false) {
            throw ValidationException::withMessages([
                'files' => ['Nepavyko apdoroti failo.'],
            ]);
        }

        if (file_put_contents($tmp, $binary) === false) {
            @unlink($tmp);
            throw ValidationException::withMessages([
                'files' => ['Nepavyko apdoroti failo.'],
            ]);
        }

        $mime = @mime_content_type($tmp) ?: 'application/octet-stream';

        return new UploadedFile($tmp, $clientFilename, $mime, null, true);
    }

    private function flattenUploadedFileInput(mixed $uploadedFiles): array
    {
        if ($uploadedFiles === null) {
            return [];
        }

        $flat = Arr::flatten(is_array($uploadedFiles) ? $uploadedFiles : [$uploadedFiles]);

        return array_values(array_filter($flat, fn ($file) => $file instanceof UploadedFile));
    }

    private function flattenUploadedFilesFromAllFiles(Request $request): array
    {
        $out = [];
        $seen = [];
        foreach ($request->allFiles() as $key => $value) {
            if (! $this->isMultipartFilesFieldKey((string) $key)) {
                continue;
            }
            $flat = Arr::flatten(is_array($value) ? $value : [$value]);
            foreach ($flat as $item) {
                if (! $item instanceof UploadedFile) {
                    continue;
                }
                $oid = spl_object_id($item);
                if (isset($seen[$oid])) {
                    continue;
                }
                $seen[$oid] = true;
                $out[] = $item;
            }
        }

        return $out;
    }

    public function isMultipartFilesFieldKey(string $key): bool
    {
        if ($key === 'files' || $key === 'files[]') {
            return true;
        }
        if (str_starts_with($key, 'files.')) {
            return true;
        }
        if (preg_match('/^files\[\d+\]$/', $key) === 1) {
            return true;
        }

        return preg_match('/^files_\d+$/', $key) === 1;
    }
}
