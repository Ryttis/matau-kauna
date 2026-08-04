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
            ->with(['attachments', 'problemNature'])
            ->where('is_published_publicly', true)
            ->whereIn('status', ProblemStatus::publicMapStatusValues())
            ->notArchived()
            ->whereNull('merged_into_problem_id')
            ->latest('created_at')
            ->paginate($perPage);

        $problems->getCollection()->transform(
            fn (Problem $problem) => $this->publicProblemPayload($problem)
        );

        return response()->json($problems);
    }

    public function show(Problem $problem): JsonResponse
    {
        if ($problem->is_archived || ! $problem->is_published_publicly) {
            abort(404);
        }

        $problem->load(['attachments', 'problemNature']);

        return response()->json($this->publicProblemPayload($problem));
    }

    /**
     * Vieša problemos reprezentacija. Sąmoningai nenaudojame toArray() - jis
     * atiduotų ir vidinius laukus (įstaigą, priskirtą darbuotoją, terminus).
     * Būsena čia visada suspausta iki pareiškėjui skirtų reikšmių.
     */
    private function publicProblemPayload(Problem $problem): array
    {
        return [
            'id' => $problem->id,
            'internal_number' => $problem->internal_number,
            ...ProblemStatus::reporterStatusPayload((string) $problem->status),
            'category' => $problem->category,
            'problem_nature_id' => $problem->problem_nature_id,
            'nature' => $problem->problemNature?->name,
            'address' => $problem->address,
            'description' => $problem->description,
            'lat' => $problem->lat !== null ? (float) $problem->lat : null,
            'lng' => $problem->lng !== null ? (float) $problem->lng : null,
            'created_at' => $problem->created_at?->toIso8601String(),
            'is_published_publicly' => $problem->is_published_publicly,
            'attachments' => $problem->attachments
                ->map(fn ($attachment) => $attachment->toPublicPayload())
                ->values()
                ->all(),
        ];
    }
}
