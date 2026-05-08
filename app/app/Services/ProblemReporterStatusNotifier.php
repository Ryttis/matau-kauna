<?php

namespace App\Services;

use App\Enums\ProblemStatus;
use App\Models\EmailNotificationTemplate;
use App\Models\Problem;
use App\Models\ProblemReviewHistory;
use App\Models\ProblemWatch;
use App\Models\User;
use App\Support\EmailBody;

class ProblemReporterStatusNotifier
{
    private const CTA_LABEL = 'Peržiūrėti problemą';

    public function statusLabel(string $status): string
    {
        $enum = ProblemStatus::tryFrom($status);

        return $enum ? $enum->label() : $status;
    }

    public function notifyRootsAfterStatusChange(array $oldStatusByRootId, string $newStatus): void
    {
        foreach ($oldStatusByRootId as $rootId => $oldStatus) {
            if ($oldStatus === null || $oldStatus === $newStatus) {
                continue;
            }
            $main = Problem::find($rootId);
            if ($main) {
                $this->notifyReportersAndWatchersWhenStatusChanges($main, $oldStatus, $newStatus);
            }
        }
    }

    public function notifyReportersAndWatchersWhenStatusChanges(Problem $mainProblem, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_REPORTER_STATUS_CHANGE);
        if (! $template || ! $template->is_enabled) {
            return;
        }

        $url = route('reports.show', $mainProblem->id);
        $replacements = [
            'internal_number' => (string) ($mainProblem->internal_number ?? ''),
            'description_excerpt' => Problem::descriptionPreview($mainProblem->description, 200),
            'url' => $url,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'old_status_label' => $this->statusLabel($oldStatus),
            'new_status_label' => $this->statusLabel($newStatus),
        ];

        $this->dispatchToReportersAndWatchers(
            $mainProblem,
            EmailBody::replacePlaceholders($template->subject, $replacements),
            EmailBody::replacePlaceholders($template->body, $replacements),
            $url,
        );
    }

    public function notifyPublicReviewEntry(Problem $mainProblem, ProblemReviewHistory $entry): void
    {
        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_REPORTER_REVIEW_ENTRY);
        if (! $template || ! $template->is_enabled) {
            return;
        }

        $url = route('reports.show', $mainProblem->id);
        $replacements = [
            'internal_number' => (string) ($mainProblem->internal_number ?? ''),
            'description_excerpt' => Problem::descriptionPreview($mainProblem->description, 200),
            'url' => $url,
            'status_label' => $this->statusLabel($entry->status),
            'responsible' => (string) ($entry->responsible ?? ''),
            'comment_excerpt' => $entry->comment
                ? Problem::descriptionPreview($entry->comment, 400)
                : '(nėra)',
            'entry_date' => $entry->created_at?->format('Y-m-d H:i') ?? '',
        ];

        $this->dispatchToReportersAndWatchers(
            $mainProblem,
            EmailBody::replacePlaceholders($template->subject, $replacements),
            EmailBody::replacePlaceholders($template->body, $replacements),
            $url,
        );
    }

    private function dispatchToReportersAndWatchers(Problem $mainProblem, string $subject, string $body, string $url): void
    {
        $mainProblem->loadMissing('mergedProblems');
        $groupIds = $mainProblem->getMergeGroupIds();

        $reporterIds = Problem::query()
            ->whereIn('id', $groupIds)
            ->whereNotNull('reported_by_user_id')
            ->pluck('reported_by_user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter();

        $reporterIdSet = $reporterIds->flip()->all();

        $watcherIds = ProblemWatch::query()
            ->whereIn('problem_id', $groupIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => isset($reporterIdSet[$id]));

        $this->sendToUsers($reporterIds, 'reporter_email_own_problems', $subject, $body, $url);
        $this->sendToUsers($watcherIds, 'reporter_email_watched_problems', $subject, $body, $url);
    }

    private function sendToUsers(iterable $userIds, string $preferenceColumn, string $subject, string $body, string $url): void
    {
        foreach ($userIds as $userId) {
            $user = User::query()
                ->notArchived()
                ->whereKey($userId)
                ->first(['id', 'email', 'email_verified_at', $preferenceColumn]);

            if (! $user || ! $user->hasVerifiedEmail() || $user->{$preferenceColumn} !== true) {
                continue;
            }

            TransactionalMail::send($user->email, $subject, $body, $url, self::CTA_LABEL);
        }
    }
}
