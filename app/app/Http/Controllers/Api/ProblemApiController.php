<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProblemStatus;
use App\Http\Controllers\Controller;
use App\Models\Problem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProblemApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $problems = Problem::query()
            ->where('is_published_publicly', true)
            ->whereIn('status', ProblemStatus::publicMapStatusValues())
            ->notArchived()
            ->whereNull('merged_into_problem_id')
            ->latest('created_at')
            ->paginate($perPage);

        return response()->json($problems);
    }

    public function show(Problem $problem): JsonResponse
    {
        if ($problem->is_archived || ! $problem->is_published_publicly) {
            abort(404);
        }

        $problem->load('attachments');

        return response()->json([
            'id' => $problem->id,
            'internal_number' => $problem->internal_number,
            'status' => $problem->status,
            'category' => $problem->category,
            'address' => $problem->address,
            'description' => $problem->description,
            'created_at' => $problem->created_at?->toIso8601String(),
            'is_published_publicly' => $problem->is_published_publicly,
            'attachments' => $problem->attachments
                ->map(fn ($attachment) => $attachment->toPublicPayload())
                ->values()
                ->all(),
        ]);
    }
}
