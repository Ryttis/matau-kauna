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

        $oldLabel = $this->statusLabel($oldStatus);
        $newLabel = $this->statusLabel($newStatus);
        $url = route('reports.show', $mainProblem->id);
        $excerpt = Problem::descriptionPreview($mainProblem->description, 200);

        $baseReplacements = [
            'internal_number' => (string) ($mainProblem->internal_number ?? ''),
            'description_excerpt' => $excerpt,
            'url' => $url,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'old_status_label' => $oldLabel,
            'new_status_label' => $newLabel,
        ];

        $subject = EmailBody::replacePlaceholders($template->subject, $baseReplacements);
        $bodyTemplate = EmailBody::replacePlaceholders($template->body, $baseReplacements);

        foreach ($reporterIds as $userId) {
            $user = User::query()
                ->notArchived()
                ->whereKey($userId)
                ->first(['id', 'email', 'email_verified_at', 'reporter_email_own_problems']);

            if (! $user || ! $user->hasVerifiedEmail() || $user->reporter_email_own_problems !== true) {
                continue;
            }

            TransactionalMail::send($user->email, $subject, $bodyTemplate, $url, 'Peržiūrėti problemą');
        }

        foreach ($watcherIds as $userId) {
            $user = User::query()
                ->notArchived()
                ->whereKey($userId)
                ->first(['id', 'email', 'email_verified_at', 'reporter_email_watched_problems']);

            if (! $user || ! $user->hasVerifiedEmail() || $user->reporter_email_watched_problems !== true) {
                continue;
            }

            TransactionalMail::send($user->email, $subject, $bodyTemplate, $url, 'Peržiūrėti problemą');
        }
    }

    public function notifyPublicReviewEntry(Problem $mainProblem, ProblemReviewHistory $entry): void
    {
        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_REPORTER_REVIEW_ENTRY);
        if (! $template || ! $template->is_enabled) {
            return;
        }

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

        $commentExcerpt = $entry->comment
            ? Problem::descriptionPreview($entry->comment, 400)
            : '(nėra)';

        $url = route('reports.show', $mainProblem->id);
        $excerpt = Problem::descriptionPreview($mainProblem->description, 200);

        $replacements = [
            'internal_number' => (string) ($mainProblem->internal_number ?? ''),
            'description_excerpt' => $excerpt,
            'url' => $url,
            'status_label' => $this->statusLabel($entry->status),
            'responsible' => (string) ($entry->responsible ?? ''),
            'comment_excerpt' => $commentExcerpt,
            'entry_date' => $entry->created_at?->format('Y-m-d H:i') ?? '',
        ];

        $subject = EmailBody::replacePlaceholders($template->subject, $replacements);
        $body = EmailBody::replacePlaceholders($template->body, $replacements);

        foreach ($reporterIds as $userId) {
            $user = User::query()
                ->notArchived()
                ->whereKey($userId)
                ->first(['id', 'email', 'email_verified_at', 'reporter_email_own_problems']);

            if (! $user || ! $user->hasVerifiedEmail() || $user->reporter_email_own_problems !== true) {
                continue;
            }

            TransactionalMail::send($user->email, $subject, $body, $url, 'Peržiūrėti problemą');
        }

        foreach ($watcherIds as $userId) {
            $user = User::query()
                ->notArchived()
                ->whereKey($userId)
                ->first(['id', 'email', 'email_verified_at', 'reporter_email_watched_problems']);

            if (! $user || ! $user->hasVerifiedEmail() || $user->reporter_email_watched_problems !== true) {
                continue;
            }

            TransactionalMail::send($user->email, $subject, $body, $url, 'Peržiūrėti problemą');
        }
    }
}
