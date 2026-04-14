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
        private ProblemScopeService $scopeService,
        private ProblemReporterStatusNotifier $reporterStatusNotifier
    ) {}

    public function visibleEntriesForProblem(Problem $problem, User $user, bool $reporterOnlyView): array
    {
        $problem->loadMissing('reviewHistory.reviewAttachments');

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
        $entry->loadMissing('reviewAttachments');

        return [
            'id' => $entry->id,
            'user_id' => $entry->user_id,
            'date' => $entry->created_at->toISOString(),
            'status' => $entry->status,
            'status_changed' => (bool) ($entry->status_changed ?? true),
            'responsible' => $entry->responsible ?? '',
            'comment' => $entry->comment ?? '',
            'visibility' => $entry->visibility ?? ProblemReviewCommentVisibility::Public->value,
            'attachments' => $entry->reviewAttachments->map(fn ($a) => $a->toPublicPayload())->values()->all(),
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

        $problem->refresh();
        $previousStatus = (string) $problem->status;
        $statusChanged = $previousStatus !== (string) $status;

        if ($statusChanged) {
            $this->applyStatusToProblemAndMerged($problem, $user, $status);
        }

        $commentNormalized = $this->normalizeComment($comment);

        $entry = ProblemReviewHistory::create([
            'problem_id' => $problem->id,
            'user_id' => $user->id,
            'status' => $status,
            'status_changed' => $statusChanged,
            'responsible' => $this->responsibleLabel($user),
            'comment' => $commentNormalized,
            'visibility' => $visibility->value,
        ]);

        if ($visibility === ProblemReviewCommentVisibility::Public) {
            $main = Problem::find($problem->merged_into_problem_id ?? $problem->id);
            if ($main) {
                $this->reporterStatusNotifier->notifyPublicReviewEntry($main, $entry);
            }
        }

        return $entry;
    }

    public function updateEntry(
        Problem $problem,
        ProblemReviewHistory $entry,
        User $user,
        string $status,
        ?string $comment,
        ProblemReviewCommentVisibility $visibility,
    ): void {
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if (! $user->hasPermission('issues.view')) {
            abort(403);
        }

        if ((int) $entry->problem_id !== (int) $problem->id) {
            abort(404);
        }

        $statusEnum = ProblemStatus::tryFrom($status);
        if ($statusEnum === null || $statusEnum === ProblemStatus::UNREGISTERED) {
            abort(422, 'Netinkama būsena');
        }

        $commentNormalized = $this->normalizeComment($comment);

        $oldStatus = $entry->status;
        $oldVisibility = $entry->visibility;

        $latestId = (int) $problem->reviewHistory()->max('id');
        $isLatest = (int) $entry->id === $latestId;

        $entry->update([
            'status' => $status,
            'comment' => $commentNormalized,
            'visibility' => $visibility->value,
        ]);

        if ($isLatest && $oldStatus !== $status) {
            $this->applyStatusToProblemAndMerged($problem, $user, $status);
        }

        if ($visibility === ProblemReviewCommentVisibility::Public
            && $oldVisibility !== ProblemReviewCommentVisibility::Public->value) {
            $main = Problem::find($problem->merged_into_problem_id ?? $problem->id);
            if ($main) {
                $this->reporterStatusNotifier->notifyPublicReviewEntry($main, $entry->fresh());
            }
        }
    }

    private function applyStatusToProblemAndMerged(Problem $problem, User $user, string $status): void
    {
        $ids = $problem->getMergeGroupIds();
        $mainId = $problem->merged_into_problem_id ?? $problem->id;
        $oldMainStatus = Problem::where('id', $mainId)->value('status');
        $query = Problem::whereIn('id', $ids);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($ids));
        $query->update([
            'status' => $status,
            'status_updated_at' => now(),
        ]);

        if ($oldMainStatus !== null && $oldMainStatus !== $status) {
            $main = Problem::find($mainId);
            if ($main) {
                $this->reporterStatusNotifier->notifyReportersAndWatchersWhenStatusChanges($main, $oldMainStatus, $status);
            }
        }
    }

    public function logStatusChangeEntry(Problem $problem, User $user, string $newStatus): void
    {
        $mainId = $problem->merged_into_problem_id ?? $problem->id;

        ProblemReviewHistory::create([
            'problem_id' => $mainId,
            'user_id' => $user->id,
            'status' => $newStatus,
            'status_changed' => true,
            'responsible' => $this->responsibleLabel($user),
            'comment' => null,
            'visibility' => ProblemReviewCommentVisibility::Public->value,
        ]);
    }

    private function normalizeComment(?string $comment): ?string
    {
        $trimmed = $comment !== null ? trim($comment) : null;

        return ($trimmed === null || $trimmed === '') ? null : $trimmed;
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
