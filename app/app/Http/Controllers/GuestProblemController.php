<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\FileUploadSettings;
use App\Models\Problem;
use App\Services\ProblemHttpSupport;
use App\Services\ProblemMapService;
use App\Services\ProblemMergeContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuestProblemController extends Controller
{
    public function __construct(
        private ProblemMapService $mapService,
        private ProblemMergeContextService $mergeContextService,
        private ProblemHttpSupport $httpSupport,
    ) {}

    public function landing(): Response
    {
        return Inertia::render('Guest/Landing', [
            'problems' => $this->mapService->getPublicProblemsForMap(),
        ]);
    }

    public function publicMap(): Response
    {
        return Inertia::render('Guest/PublicMap', [
            'problems' => $this->mapService->getPublicProblemsForMap(),
        ]);
    }

    public function publicShow(Request $request, Problem $problem): Response
    {
        if ($problem->is_archived) {
            abort(404);
        }

        $user = $request->user();
        $isReporterOnly = $user && $user->isReporterOnly();
        $isReporterOwnProblem = $isReporterOnly && (int) $problem->reported_by_user_id === (int) $user->id;
        $isPublic = $problem->is_published_publicly;

        if (! $isReporterOwnProblem && ! $isPublic) {
            if (! $user) {
                session()->put('url.intended', url()->current());

                return Inertia::render('Guest/LoginRequired');
            }

            abort(404);
        }

        $problem->load(['attachments', 'problemNature']);

        $problemData = [
            'id' => $problem->id,
            'internal_number' => $problem->internal_number,
            ...ProblemStatus::reporterStatusPayload((string) $problem->status),
            'category' => $problem->category,
            'nature' => $problem->problemNature?->name,
            'address' => $problem->address,
            'description' => $problem->description,
            'created_at' => $problem->created_at?->toIso8601String(),
            'is_published_publicly' => $problem->is_published_publicly,
            'attachments' => $this->httpSupport->attachmentsPayload($problem),
        ];

        $mergeContext = $this->mergeContextService->forProblem($problem);
        $reviewProgressProblemId = $problem->merged_into_problem_id && $problem->mergedInto
            ? $problem->mergedInto->id
            : $problem->id;

        if ($isReporterOnly) {
            $canWatch = $this->httpSupport->reporterCanToggleWatch($user, $problem);
            $isWatched = $canWatch && $this->httpSupport->problemIsWatchedByUser($user, $problem);

            return Inertia::render('Reporter/ProblemShow', [
                'problem' => $problemData,
                'mergeTarget' => $mergeContext['mergeTarget'],
                'mergedChildren' => $mergeContext['mergedChildren'],
                'mergedSiblings' => $mergeContext['mergedSiblings'],
                'reviewProgressProblemId' => $reviewProgressProblemId,
                'backFrom' => $request->query('from'),
                'backOnlyMine' => $request->boolean('only_mine'),
                'fileUpload' => FileUploadSettings::getChannelForClient(FileUploadSettings::CHANNEL_WEB),
                'canUploadAttachments' => false,
                'canWatch' => $canWatch,
                'isWatched' => $isWatched,
            ]);
        }

        return Inertia::render('Guest/PublicProblemShow', [
            'problem' => $problemData,
            'mergeTarget' => $mergeContext['mergeTarget'],
            'mergedChildren' => $mergeContext['mergedChildren'],
            'mergedSiblings' => $mergeContext['mergedSiblings'],
        ]);
    }
}
