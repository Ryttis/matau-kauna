<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\ClassifierValue;
use App\Services\ProblemMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProblemMapController extends Controller
{
    public function __construct(
        private ProblemMapService $mapService,
    ) {}

    public function map(): Response
    {
        $user = request()->user();
        $categories = ClassifierValue::getActiveTypeNames()->values();

        return Inertia::render('Map', [
            'categories' => $categories,
            'statusOptions' => ProblemStatus::selectableOptions(),
            'problems' => $this->mapService->getProblemsForMapForUser($user),
        ]);
    }

    public function addressSearch(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $mode = $request->input('mode', 'all');
        $mode = is_string($mode) && in_array($mode, ['all', 'number', 'address'], true) ? $mode : 'all';
        $results = $this->mapService->searchByAddress(
            $request->user(),
            is_string($query) ? $query : '',
            $mode
        );

        return response()->json($results);
    }
}
