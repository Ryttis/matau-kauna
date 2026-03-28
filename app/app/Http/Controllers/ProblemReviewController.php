<?php

namespace App\Http\Controllers;

use App\Enums\ProblemReviewCommentVisibility;
use App\Http\Requests\StoreProblemReviewHistoryRequest;
use App\Models\FileUploadSettings;
use App\Models\Problem;
use App\Models\ProblemReviewAttachment;
use App\Models\ProblemReviewHistory;
use App\Models\UserRole;
use App\Services\ProblemHttpSupport;
use App\Services\ProblemReviewHistoryService;
use App\Services\ProblemScopeService;
use App\Services\ReviewEntryFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Response;

class ProblemReviewController extends Controller
{
    public function __construct(
        private ProblemHttpSupport $httpSupport,
        private ProblemScopeService $scopeService,
        private ProblemReviewHistoryService $reviewHistoryService,
        private ReviewEntryFileUploadService $reviewEntryFileUploadService,
    ) {}

    public function reviewProgress(Problem $problem): Response
    {
        $user = request()->user();
        if (! $user) {
            abort(403);
        }
        if ($problem->is_archived) {
            abort(404);
        }

        $isReporterOnly = $user->hasRole(UserRole::ROLE_REPORTER) && ! $user->hasPermission('issues.view');
        if ($isReporterOnly) {
            if ($problem->merged_into_problem_id) {
                $problem->load('mergedInto');
                $mainProblem = $problem->mergedInto ?? $problem;
            } else {
                $mainProblem = $problem;
            }
            if ($mainProblem->is_archived) {
                abort(404);
            }
            $problem = $mainProblem;
            $problem->load('reviewHistory');

            return $this->httpSupport->reviewProgressResponse($problem, true);
        }

        $this->scopeService->authorizeProblemAccess($user, $problem);
        if (! $user->hasPermission('issues.view')) {
            abort(403);
        }

        $problem->load('reviewHistory');

        return $this->httpSupport->reviewProgressResponse($problem, false);
    }

    public function storeReviewProgress(StoreProblemReviewHistoryRequest $request, Problem $problem): RedirectResponse
    {
        $validated = $request->validated();
        $entry = $this->reviewHistoryService->storeEntry(
            $problem,
            $request->user(),
            $validated['status'],
            $validated['comment'] ?? null,
            ProblemReviewCommentVisibility::from($validated['visibility'])
        );

        $files = $this->httpSupport->normalizedUploadedFiles($request);
        if ($files !== []) {
            $channel = FileUploadSettings::resolveUploadChannel($request->header('X-Upload-Channel'));
            $this->reviewEntryFileUploadService->validateAndStore($problem, $entry, $files, $channel);
        }

        return redirect()->back()->with('success', 'Įrašas išsaugotas.');
    }

    public function updateReviewProgress(StoreProblemReviewHistoryRequest $request, Problem $problem, ProblemReviewHistory $entry): RedirectResponse
    {
        if ((int) $entry->problem_id !== (int) $problem->id) {
            abort(404);
        }

        $validated = $request->validated();
        $this->reviewHistoryService->updateEntry(
            $problem,
            $entry,
            $request->user(),
            $validated['status'],
            $validated['comment'] ?? null,
            ProblemReviewCommentVisibility::from($validated['visibility'])
        );

        $files = $this->httpSupport->normalizedUploadedFiles($request);
        if ($files !== []) {
            $channel = FileUploadSettings::resolveUploadChannel($request->header('X-Upload-Channel'));
            $entry->refresh();
            $this->reviewEntryFileUploadService->validateAndStore($problem, $entry, $files, $channel);
        }

        return redirect()->back()->with('success', 'Įrašas atnaujintas.');
    }

    public function destroyReviewAttachment(Request $request, Problem $problem, ProblemReviewAttachment $attachment): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('issues.view')) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if (! $this->httpSupport->userCanDeleteReviewAttachment($user, $problem)) {
            abort(403);
        }

        $attachment->loadMissing('reviewHistory');
        if (! $attachment->reviewHistory || (int) $attachment->reviewHistory->problem_id !== (int) $problem->id) {
            abort(404);
        }

        $absolute = public_path(ltrim($attachment->path, '/'));
        if ($absolute !== '' && File::exists($absolute)) {
            File::delete($absolute);
        }

        $attachment->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('success', 'Priedas pašalintas.');
    }
}
