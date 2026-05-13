<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateArchiveRetentionRequest;
use App\Models\ClassifierValue;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use App\Services\ArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ArchiveController extends Controller
{
    public function __construct(
        private ArchiveService $archiveService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->archiveService->getIndexData());
    }

    public function updateRetentionRule(UpdateArchiveRetentionRequest $request): JsonResponse
    {
        $this->archiveService->updateRetentionRule($request->validated());

        return response()->json(['ok' => true]);
    }

    public function destroyUser(User $user): JsonResponse
    {
        if ((int) $user->id === (int) Auth::id()) {
            return response()->json(['message' => 'Negalima pašalinti savo paskyros.'], 422);
        }

        $user->delete();

        return response()->json(['ok' => true]);
    }

    public function restoreUser(User $user): JsonResponse
    {
        if (! $user->archived_at) {
            return response()->json(['message' => 'Naudotojas nearchyvuotas.'], 422);
        }
        $user->update(['archived_at' => null]);

        return response()->json(['ok' => true]);
    }

    public function restoreInstitution(Institution $institution): JsonResponse
    {
        if (! $institution->archived_at) {
            return response()->json(['message' => 'Įstaiga nearchyvuota.'], 422);
        }
        $institution->update(['archived_at' => null]);

        return response()->json(['ok' => true]);
    }

    public function destroyInstitution(Institution $institution): JsonResponse
    {
        $institution->delete();

        return response()->json(['ok' => true]);
    }

    public function restoreProblem(Problem $problem): JsonResponse
    {
        if (! $problem->is_archived) {
            return response()->json(['message' => 'Problema nearchyvuota.'], 422);
        }
        $problem->update(['is_archived' => false, 'archived_at' => null]);

        return response()->json(['ok' => true]);
    }

    public function destroyProblem(Problem $problem): JsonResponse
    {
        $problem->delete();

        return response()->json(['ok' => true]);
    }

    public function restoreClassifierValue(ClassifierValue $classifier_value): JsonResponse
    {
        if (! $classifier_value->archived_at) {
            return response()->json(['message' => 'Įrašas nearchyvuotas.'], 422);
        }
        $classifier_value->update(['archived_at' => null]);
        if ($classifier_value->parent_id === null) {
            ClassifierValue::where('parent_id', $classifier_value->id)->update(['archived_at' => null]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroyClassifierValue(ClassifierValue $classifier_value): JsonResponse
    {
        if ($classifier_value->parent_id === null) {
            ClassifierValue::where('parent_id', $classifier_value->id)->delete();
        }
        $classifier_value->delete();

        return response()->json(['ok' => true]);
    }
}
