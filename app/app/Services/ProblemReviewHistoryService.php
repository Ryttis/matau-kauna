<?php

namespace App\Services;

use App\Enums\ProblemReviewCommentVisibility;
use App\Enums\ProblemStatus;
use App\Models\Problem;
use App\Models\ProblemReviewHistory;
use App\Models\User;

class ProblemReviewHistoryService
{
    public const INTERNAL_NOTE_RETURNED_TO_ADMIN = 'Problema grąžinta problemų administratoriui - įstaigos priskyrimas nuimtas.';

    public const SYSTEM_RESPONSIBLE = 'Sistema';

    public const SYSTEM_RESPONSIBLE_DVS = 'Sistema (DVS)';

    public const SYSTEM_RESPONSIBLE_GIS = 'Sistema (GIS)';

    public function __construct(
        private ProblemScopeService $scopeService,
        private ProblemReporterStatusNotifier $reporterStatusNotifier
    ) {}

    public function visibleEntriesForProblem(Problem $problem, User $user, bool $reporterOnlyView): array
    {
        if ($reporterOnlyView) {
            $problem->loadMissing('reviewHistory.reviewAttachments');

            $rows = $problem->reviewHistory->where(
                'visibility',
                ProblemReviewCommentVisibility::Public->value
            );
        } else {
            // Būsenos įrašai rašomi į sujungimo grupės šakninę problemą, todėl
            // darbuotojams rodome visos grupės istoriją - kitaip atidarius sujungtą
            // "vaikinę" problemą žurnalas atrodo tuščias.
            $rows = ProblemReviewHistory::query()
                ->whereIn('problem_id', $problem->getMergeGroupIds())
                ->with('reviewAttachments')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();
        }

        return $rows
            ->map(function (ProblemReviewHistory $entry) use ($reporterOnlyView) {
                $row = $this->entryToArray($entry);
                if ($reporterOnlyView) {
                    $row['responsible'] = '';
                }
                return $row;
            })
            ->values()
            ->all();
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

        if (! $this->userCanWriteReviewProgress($user)) {
            abort(403);
        }

        $statusEnum = ProblemStatus::tryFrom($status);
        if ($statusEnum === null || $statusEnum === ProblemStatus::UNREGISTERED) {
            abort(422, 'Netinkama būsena');
        }

        $problem->refresh();
        $previousStatus = (string) $problem->status;
        $statusChanged = $previousStatus !== (string) $status;

        $publicReviewEntryWillCoverStatus = $statusChanged
            && $visibility === ProblemReviewCommentVisibility::Public;

        if ($statusChanged) {
            $this->applyStatusToProblemAndMerged($problem, $user, $status, ! $publicReviewEntryWillCoverStatus);
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

    public function storeAutomatedInternalNote(Problem $problem, User $user, string $comment): ProblemReviewHistory
    {
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $problem->refresh();

        return ProblemReviewHistory::create([
            'problem_id' => $problem->id,
            'user_id' => $user->id,
            'status' => (string) $problem->status,
            'status_changed' => false,
            'responsible' => $this->responsibleLabel($user),
            'comment' => $this->normalizeComment($comment),
            'visibility' => ProblemReviewCommentVisibility::Internal->value,
        ]);
    }

    /**
     * Vidinis (tik darbuotojams matomas) įvykio įrašas - įstaigos perdavimas,
     * priskyrimas darbuotojui ir pan. Autorizaciją atlieka kviečiantis kontroleris,
     * nes įrašas dažnai rašomas prieš pakeičiant problemos įstaigą.
     */
    public function logInternalEventEntry(
        Problem $problem,
        ?User $user,
        string $comment,
        ?string $statusOverride = null,
    ): ProblemReviewHistory {
        return ProblemReviewHistory::create([
            'problem_id' => $problem->merged_into_problem_id ?? $problem->id,
            'user_id' => $user?->id,
            'status' => $statusOverride ?? (string) $problem->status,
            'status_changed' => $statusOverride !== null,
            'responsible' => $this->responsibleLabelFor($user),
            'comment' => $this->normalizeComment($comment),
            'visibility' => ProblemReviewCommentVisibility::Internal->value,
        ]);
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

        if (! $this->userCanWriteReviewProgress($user)) {
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
        $problem->refresh();
        $statusWouldChangeProblem = $isLatest && (string) $problem->status !== (string) $status;
        $statusChangedForEntry = $oldStatus !== (string) $status
            ? $statusWouldChangeProblem
            : (bool) ($entry->status_changed ?? true);

        $entry->update([
            'status' => $status,
            'status_changed' => $statusChangedForEntry,
            'comment' => $commentNormalized,
            'visibility' => $visibility->value,
        ]);

        $newVisibilityIsPublic = $visibility === ProblemReviewCommentVisibility::Public;
        $becomingNewlyPublic = $newVisibilityIsPublic
            && $oldVisibility !== ProblemReviewCommentVisibility::Public->value;
        $publicReviewEntryWillCoverStatus = $newVisibilityIsPublic && $statusWouldChangeProblem;

        if ($statusWouldChangeProblem) {
            $this->applyStatusToProblemAndMerged($problem, $user, $status, ! $publicReviewEntryWillCoverStatus);
        }

        if ($becomingNewlyPublic) {
            $main = Problem::find($problem->merged_into_problem_id ?? $problem->id);
            if ($main) {
                $this->reporterStatusNotifier->notifyPublicReviewEntry($main, $entry->fresh());
            }
        }
    }

    private function applyStatusToProblemAndMerged(Problem $problem, User $user, string $status, bool $notifyStatusChange = true): void
    {
        $ids = $problem->getMergeGroupIds();
        $mainId = $problem->merged_into_problem_id ?? $problem->id;
        $oldMainStatus = Problem::where('id', $mainId)->value('status');
        $query = Problem::whereIn('id', $ids);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($ids));
        $changedIds = (clone $query)
            ->where('status', '!=', $status)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($changedIds === []) {
            return;
        }

        $statusUpdate = [
            'status' => $status,
            'status_updated_at' => now(),
        ];
        if (in_array($status, Problem::NON_NOTIFIABLE_STATUSES, true)) {
            $statusUpdate['deadline_status'] = null;
            $statusUpdate['deadline_reminder_sent_at'] = null;
        }
        Problem::whereIn('id', $changedIds)->update($statusUpdate);

        if ($notifyStatusChange && $oldMainStatus !== null && $oldMainStatus !== $status) {
            $main = Problem::find($mainId);
            if ($main) {
                $this->reporterStatusNotifier->notifyReportersAndWatchersWhenStatusChanges($main, $oldMainStatus, $status);
            }
        }
    }

    public function logStatusChangeEntry(
        Problem $problem,
        ?User $user,
        string $newStatus,
        ?string $responsibleOverride = null,
    ): void {
        $mainId = $problem->merged_into_problem_id ?? $problem->id;

        ProblemReviewHistory::create([
            'problem_id' => $mainId,
            'user_id' => $user?->id,
            'status' => $newStatus,
            'status_changed' => true,
            'responsible' => $responsibleOverride ?? $this->responsibleLabelFor($user),
            'comment' => null,
            'visibility' => ProblemReviewCommentVisibility::Public->value,
        ]);
    }

    public static function institutionChangeNote(?string $from, ?string $to): string
    {
        $from = trim((string) $from);
        $to = trim((string) $to);

        if ($from !== '' && $to !== '') {
            return 'Problema perduota iš įstaigos „'.$from.'“ į įstaigą „'.$to.'“.';
        }

        if ($to !== '') {
            return 'Problema priskirta įstaigai „'.$to.'“.';
        }

        if ($from !== '') {
            return 'Problema atšaukta nuo įstaigos „'.$from.'“.';
        }

        return 'Problemos įstaigos priskyrimas pakeistas.';
    }

    public static function employeeAssignmentNote(string $employeeName): string
    {
        $name = trim($employeeName);

        return $name !== ''
            ? 'Problema priskirta darbuotojui „'.$name.'“.'
            : 'Problema priskirta darbuotojui.';
    }

    public static function teamAssignmentNote(string $teamName): string
    {
        $name = trim($teamName);

        return $name !== ''
            ? 'Problema priskirta komandai „'.$name.'“.'
            : 'Problema priskirta komandai.';
    }

    private function normalizeComment(?string $comment): ?string
    {
        $trimmed = $comment !== null ? trim($comment) : null;

        return ($trimmed === null || $trimmed === '') ? null : $trimmed;
    }

    private function userCanWriteReviewProgress(User $user): bool
    {
        return $user->canAccessAdmin() || $user->hasPermission('issues.edit');
    }

    private function responsibleLabel(User $user): string
    {
        $name = trim((string) $user->name.' '.(string) $user->surname);

        return $name !== '' ? $name : (string) ($user->email ?? '');
    }

    private function responsibleLabelFor(?User $user): string
    {
        return $user !== null ? $this->responsibleLabel($user) : self::SYSTEM_RESPONSIBLE;
    }

    public static function allowedStatusesForReviewEntry(): array
    {
        return array_values(array_filter(
            ProblemStatus::values(),
            fn (string $v) => ! in_array($v, [
                ProblemStatus::UNREGISTERED->value,
                ProblemStatus::NEW->value,
                ProblemStatus::ASSIGNED->value,
                ProblemStatus::RETURNED_FOR_REVIEW->value,
            ], true)
        ));
    }
}
