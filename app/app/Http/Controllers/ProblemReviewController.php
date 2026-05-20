<?php

namespace App\Http\Controllers;

use App\Enums\ProblemReviewCommentVisibility;
use App\Http\Requests\StoreProblemReviewHistoryRequest;
use App\Models\FileUploadSettings;
use App\Models\Problem;
use App\Models\ProblemReviewAttachment;
use App\Models\ProblemReviewHistory;
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

    public function reviewProgress(Request $request, Problem $problem): Response|JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }
        if ($problem->is_archived) {
            abort(404);
        }

        $isReporterOnly = $user->isReporterOnly();
        $reporterBackToDashboard = false;
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
            $reporterBackToDashboard = true;
        }
        if (! $isReporterOnly) {
            $this->scopeService->authorizeProblemAccess($user, $problem);
            if (! $user->hasPermission('issues.view')) {
                abort(403);
            }

            $problem->load('reviewHistory');
        }

        if ($request->expectsJson()) {
            $canWriteReviewProgress = ! $reporterBackToDashboard
                && ($user->canAccessAdmin() || $user->hasPermission('issues.edit'));
            $reviewEntries = $this->reviewHistoryService->visibleEntriesForProblem(
                $problem,
                $user,
                $reporterBackToDashboard
            );

            return response()->json([
                'problem_id' => $problem->id,
                'reviewEntries' => $reviewEntries,
                'canAddEntry' => $canWriteReviewProgress,
                'canEditReviewProgress' => $canWriteReviewProgress,
                'canDeleteReviewAttachments' => $canWriteReviewProgress
                    && $this->httpSupport->userCanDeleteReviewAttachment($user, $problem),
                'fileUpload' => FileUploadSettings::getReviewProgressChannelForClient(),
            ]);
        }

        return $this->httpSupport->reviewProgressResponse($problem, $reporterBackToDashboard);
    }

    public function storeReviewProgress(StoreProblemReviewHistoryRequest $request, Problem $problem): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $entry = $this->reviewHistoryService->storeEntry(
            $problem,
            $request->user(),
            $validated['status'],
            $validated['comment'] ?? null,
            ProblemReviewCommentVisibility::from($validated['visibility'])
        );

        $files = $this->httpSupport->uploadedFilesForStorage($request);
        if ($files !== []) {
            $channel = FileUploadSettings::resolveUploadChannel($request->header('X-Upload-Channel'));
            $this->reviewEntryFileUploadService->validateAndStore($problem, $entry, $files, $channel);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Įrašas išsaugotas.',
                'entry_id' => $entry->id,
            ]);
        }

        return redirect()->back()->with('success', 'Įrašas išsaugotas.');
    }

    public function updateReviewProgress(StoreProblemReviewHistoryRequest $request, Problem $problem, ProblemReviewHistory $entry): RedirectResponse|JsonResponse
    {
        if ((int) $entry->problem_id !== (int) $problem->id) {
            abort(404);
        }

        $user = $request->user();
        if (! $user->canAccessAdmin()) {
            if ($entry->user_id === null || (int) $entry->user_id !== (int) $user->id) {
                abort(403);
            }
        }

        $validated = $request->validated();
        $status = $validated['status'] ?? $entry->status;
        $visibilityValue = $validated['visibility'] ?? $entry->visibility
            ?? ProblemReviewCommentVisibility::Public->value;
        $visibility = ProblemReviewCommentVisibility::tryFrom((string) $visibilityValue)
            ?? ProblemReviewCommentVisibility::Public;
        $comment = array_key_exists('comment', $validated)
            ? $validated['comment']
            : $entry->comment;

        $this->reviewHistoryService->updateEntry(
            $problem,
            $entry,
            $request->user(),
            $status,
            $comment,
            $visibility
        );

        $files = $this->httpSupport->uploadedFilesForStorage($request);
        if ($files !== []) {
            $channel = FileUploadSettings::resolveUploadChannel($request->header('X-Upload-Channel'));
            $entry->refresh();
            $this->reviewEntryFileUploadService->validateAndStore($problem, $entry, $files, $channel);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Įrašas atnaujintas.',
                'entry_id' => $entry->id,
            ]);
        }

        return redirect()->back()->with('success', 'Įrašas atnaujintas.');
    }

    public function destroyReviewAttachment(Request $request, int|string $problem, int|string $attachment): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('issues.view')) {
            abort(403);
        }

        $problemModel = Problem::query()->find((int) $problem);
        if ($problemModel === null) {
            abort(404, 'Problema nerasta (blogas problem id).');
        }

        $this->scopeService->authorizeProblemAccess($user, $problemModel);

        if (! $this->httpSupport->userCanDeleteReviewAttachment($user, $problemModel)) {
            abort(403);
        }

        $attachmentId = (int) $attachment;

        $attachmentModel = ProblemReviewAttachment::query()
            ->whereKey($attachmentId)
            ->with('reviewHistory')
            ->first();

        if ($attachmentModel === null) {
            $byEntry = ProblemReviewAttachment::query()
                ->where('problem_review_history_id', $attachmentId)
                ->with('reviewHistory')
                ->get();
            if ($byEntry->count() === 1) {
                $attachmentModel = $byEntry->first();
            } elseif ($byEntry->count() > 1) {
                abort(422, 'Šiam įrašui keletas priedų - nurodykite priedo id (attachments[].id), ne įrašo id.');
            } else {
                abort(404, 'Priedas nerastas (neteisingas id: dažnai painiojama su nagrinėjimo įrašo id).');
            }
        }
        if ($attachmentModel->reviewHistory === null) {
            abort(404, 'Priedas be nagrinėjimo įrašo (sugadinti duomenys).');
        }
        $entryProblem = Problem::query()->find((int) $attachmentModel->reviewHistory->problem_id);
        if ($entryProblem === null) {
            abort(404, 'Nerasta problema, kuriai priklauso šis įrašas.');
        }
        $sameMergeTree = (int) $problemModel->id === (int) $entryProblem->id
            || $problemModel->mergeGroupRootId() === $entryProblem->mergeGroupRootId();
        if (! $sameMergeTree) {
            abort(404, 'Priedas nepriklauso šiai problemai (skirtinga sujungtų problemų grupė).');
        }

        $absolute = public_path(ltrim($attachmentModel->path, '/'));
        if ($absolute !== '' && File::exists($absolute)) {
            File::delete($absolute);
        }

        $attachmentModel->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('success', 'Priedas pašalintas.');
    }
}
