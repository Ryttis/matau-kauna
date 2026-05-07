<?php

namespace App\Http\Controllers;

use App\Models\Problem;
use App\Models\ProblemWatch;
use App\Services\ProblemHttpSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProblemWatchController extends Controller
{
    public function __construct(
        private ProblemHttpSupport $httpSupport,
    ) {}

    public function toggle(Request $request, Problem $problem): JsonResponse
    {
        if ($problem->is_archived) {
            abort(404);
        }

        $user = $request->user();
        if (! $this->httpSupport->userCanToggleWatch($user, $problem)) {
            abort(404);
        }

        $mainId = $problem->merged_into_problem_id ?? $problem->id;

        $existing = ProblemWatch::query()
            ->where('user_id', $user->id)
            ->where('problem_id', $mainId)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['watched' => false]);
        }

        ProblemWatch::create([
            'user_id' => $user->id,
            'problem_id' => $mainId,
        ]);

        return response()->json(['watched' => true]);
    }
}
