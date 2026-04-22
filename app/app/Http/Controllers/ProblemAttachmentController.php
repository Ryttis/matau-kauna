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

        $request->validate(
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
        );

        $channel = FileUploadSettings::resolveUploadChannel($request->header('X-Upload-Channel'));

        $this->reporterFileUploadService->validateAndStore(
            $problem,
            $this->httpSupport->normalizedUploadedFiles($request),
            $channel,
            true,
            false
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
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
