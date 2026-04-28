<?php

namespace App\Http\Controllers;

use App\Models\FileUploadSettings;
use App\Models\Problem;
use App\Models\ProblemAttachment;
use App\Services\ProblemHttpSupport;
use App\Services\ProblemScopeService;
use App\Services\ReporterFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class ProblemAttachmentController extends Controller
{
    public function __construct(
        private ProblemHttpSupport $httpSupport,
        private ProblemScopeService $scopeService,
        private ReporterFileUploadService $reporterFileUploadService,
    ) {}

    public function storeAttachments(Request $request, Problem $problem): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $reporterOwn = $user->isReporterOnly() && (int) $problem->reported_by_user_id === (int) $user->id;
        if ($user->canEditProblemClassification()) {
            $this->scopeService->authorizeProblemAccess($user, $problem);
        } elseif (! $reporterOwn) {
            abort(403);
        }

        $normalizedFiles = $this->httpSupport->uploadedFilesForStorage($request);

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

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'attachments' => collect($created)
                    ->map(fn (ProblemAttachment $attachment) => $attachment->toPublicPayload())
                    ->values()
                    ->all(),
            ]);
        }

        return redirect()->back()->with('success', 'Failai įkelti.');
    }

    public function destroyAttachment(Request $request, Problem $problem, ProblemAttachment $attachment): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if ($user->canEditProblemClassification()) {
            $this->scopeService->authorizeProblemAccess($user, $problem);
        } elseif (! $user->isReporterOnly() || (int) $problem->reported_by_user_id !== (int) $user->id) {
            abort(403);
        }

        if ((int) $attachment->problem_id !== (int) $problem->id) {
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
