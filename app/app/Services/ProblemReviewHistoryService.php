<?php

namespace App\Services;

use App\Enums\ProblemReviewCommentVisibility;
use App\Enums\ProblemStatus;
use App\Models\Problem;
use App\Models\ProblemReviewHistory;
use App\Models\User;

class ProblemReviewHistoryService
{
    public function __construct(
        private ProblemScopeService $scopeService
    ) {}

    public function visibleEntriesForProblem(Problem $problem, User $user, bool $reporterOnlyView): array
    {
        $problem->loadMissing('reviewHistory');

        $rows = $problem->reviewHistory;
        if ($reporterOnlyView) {
            $rows = $rows->where(
                'visibility',
                ProblemReviewCommentVisibility::Public->value
            );
        }

        return $rows->map(fn (ProblemReviewHistory $entry) => $this->entryToArray($entry))->values()->all();
    }

    public function entryToArray(ProblemReviewHistory $entry): array
    {
        return [
            'id' => $entry->id,
            'date' => $entry->created_at->toISOString(),
            'status' => $entry->status,
            'responsible' => $entry->responsible ?? '',
            'comment' => $entry->comment ?? '',
            'visibility' => $entry->visibility ?? ProblemReviewCommentVisibility::Public->value,
        ];
    }

    public function storeEntry(
        Problem $problem,
        User $user,
        string $status,
        ?string $comment,
        ProblemReviewCommentVisibility $visibility
    ): ProblemReviewHistory {
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if (! $user->hasPermission('issues.view')) {
            abort(403);
        }

        $statusEnum = ProblemStatus::tryFrom($status);
        if ($statusEnum === null || $statusEnum === ProblemStatus::UNREGISTERED) {
            abort(422, 'Netinkama būsena');
        }

        $this->applyStatusToProblemAndMerged($problem, $user, $status);

        $commentNormalized = $comment !== null ? trim($comment) : null;
        if ($commentNormalized === '') {
            $commentNormalized = null;
        }

        return ProblemReviewHistory::create([
            'problem_id' => $problem->id,
            'status' => $status,
            'responsible' => $this->responsibleLabel($user),
            'comment' => $commentNormalized,
            'visibility' => $visibility->value,
        ]);
    }

    private function applyStatusToProblemAndMerged(Problem $problem, User $user, string $status): void
    {
        $ids = $problem->getMergeGroupIds();
        $query = Problem::whereIn('id', $ids);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($ids));
        $query->update([
            'status' => $status,
            'status_updated_at' => now(),
        ]);
    }

    private function responsibleLabel(User $user): string
    {
        $name = trim((string) $user->name.' '.(string) $user->surname);

        return $name !== '' ? $name : (string) ($user->email ?? '');
    }

    public static function allowedStatusesForReviewEntry(): array
    {
        return array_values(array_filter(
            ProblemStatus::values(),
            fn (string $v) => $v !== ProblemStatus::UNREGISTERED->value
        ));
    }
}
