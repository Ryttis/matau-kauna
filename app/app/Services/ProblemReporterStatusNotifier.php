<?php

namespace App\Services;

use App\Enums\ProblemStatus;
use App\Models\EmailNotificationTemplate;
use App\Models\Problem;
use App\Models\ProblemReviewHistory;
use App\Models\ProblemWatch;
use App\Models\User;
use App\Notifications\PushNotification;
use App\Support\EmailBody;

class ProblemReporterStatusNotifier
{
    public const PUSH_ON_STATUS_CHANGE_ENABLED = true;

    private const CTA_LABEL = 'Peržiūrėti problemą';

    public function statusLabel(string $status): string
    {
        return ProblemStatus::reporterStatusLabel($status);
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
        if (! $this->canNotifyStatusChange($mainProblem, $oldStatus, $newStatus)) {
            return;
        }

        if (ProblemStatus::reporterStatusValue($oldStatus) === ProblemStatus::reporterStatusValue($newStatus)) {
            return;
        }

        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_REPORTER_STATUS_CHANGE);
        if (! $template || ! $template->is_enabled) {
            $this->dispatchStatusChangePush($mainProblem, $oldStatus, $newStatus);

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

        $this->dispatchStatusChangePush($mainProblem, $oldStatus, $newStatus);
    }

    public function notifyPublicReviewEntry(Problem $mainProblem, ProblemReviewHistory $entry): void
    {
        if (! $this->canNotifyReviewEntry($mainProblem, $entry)) {
            return;
        }

        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_REPORTER_REVIEW_ENTRY);
        if ($template && $template->is_enabled) {
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
                'entry_date' => $entry->created_at?->timezone('Europe/Vilnius')->format('Y-m-d H:i') ?? '',
            ];

            $this->dispatchToReportersAndWatchers(
                $mainProblem,
                EmailBody::replacePlaceholders($template->subject, $replacements),
                EmailBody::replacePlaceholders($template->body, $replacements),
                $url,
            );
        }

        $this->dispatchReviewEntryPush($mainProblem, $entry);
    }

    private function canNotifyStatusChange(Problem $mainProblem, string $oldStatus, string $newStatus): bool
    {
        if ($mainProblem->is_archived) {
            return false;
        }

        return $mainProblem->isNotificationActive()
            || ($this->isTerminalStatus($newStatus) && ! $this->isTerminalStatus($oldStatus));
    }

    private function canNotifyReviewEntry(Problem $mainProblem, ProblemReviewHistory $entry): bool
    {
        if ($mainProblem->is_archived) {
            return false;
        }

        return $mainProblem->isNotificationActive()
            || ((bool) $entry->status_changed && $this->isTerminalStatus((string) $entry->status));
    }

    private function isTerminalStatus(string $status): bool
    {
        return in_array($status, Problem::NON_NOTIFIABLE_STATUSES, true);
    }

    private function dispatchReviewEntryPush(Problem $mainProblem, ProblemReviewHistory $entry): void
    {
        if (! self::PUSH_ON_STATUS_CHANGE_ENABLED) {
            return;
        }

        $mainProblem->loadMissing('mergedProblems');
        $groupIds = $mainProblem->getMergeGroupIds();

        $reporterIds = Problem::query()
            ->whereIn('id', $groupIds)
            ->whereNotNull('reported_by_user_id')
            ->pluck('reported_by_user_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->filter();

        $reporterIdSet = $reporterIds->flip()->all();

        $watcherIds = ProblemWatch::query()
            ->whereIn('problem_id', $groupIds)
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->reject(fn($id) => isset($reporterIdSet[$id]));

        $statusLabel = $this->statusLabel((string) $entry->status);
        $title = 'Pranešimo eigos įrašas';
        $body = sprintf(
            '%s · %s',
            (string) ($mainProblem->internal_number ?? 'Pranešimas'),
            $statusLabel,
        );
        $data = [
            'type' => 'problem_review_entry',
            'problem_id' => (string) $mainProblem->id,
            'internal_number' => (string) ($mainProblem->internal_number ?? ''),
            'entry_id' => (string) $entry->id,
            'status' => (string) $entry->status,
            'status_label' => $statusLabel,
        ];

        $this->pushToUsers($reporterIds, 'reporter_email_own_problems', $title, $body, $data);
        $this->pushToUsers($watcherIds, 'reporter_email_watched_problems', $title, $body, $data);
    }

    private function dispatchToReportersAndWatchers(Problem $mainProblem, string $subject, string $body, string $url): void
    {
        $mainProblem->loadMissing('mergedProblems');
        $groupIds = $mainProblem->getMergeGroupIds();

        $reporterIds = Problem::query()
            ->whereIn('id', $groupIds)
            ->whereNotNull('reported_by_user_id')
            ->pluck('reported_by_user_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->filter();

        $reporterIdSet = $reporterIds->flip()->all();

        $watcherIds = ProblemWatch::query()
            ->whereIn('problem_id', $groupIds)
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->reject(fn($id) => isset($reporterIdSet[$id]));

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

    private function dispatchStatusChangePush(Problem $mainProblem, string $oldStatus, string $newStatus): void
    {
        if (! self::PUSH_ON_STATUS_CHANGE_ENABLED) {
            return;
        }

        $mainProblem->loadMissing('mergedProblems');
        $groupIds = $mainProblem->getMergeGroupIds();

        $reporterIds = Problem::query()
            ->whereIn('id', $groupIds)
            ->whereNotNull('reported_by_user_id')
            ->pluck('reported_by_user_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->filter();

        $reporterIdSet = $reporterIds->flip()->all();

        $watcherIds = ProblemWatch::query()
            ->whereIn('problem_id', $groupIds)
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->reject(fn($id) => isset($reporterIdSet[$id]));

        $title = 'Pranešimo būsena pasikeitė';
        $body = sprintf(
            '%s: %s → %s',
            (string) ($mainProblem->internal_number ?? 'Pranešimas'),
            $this->statusLabel($oldStatus),
            $this->statusLabel($newStatus),
        );
        $data = [
            'type' => 'problem_status_change',
            'problem_id' => (string) $mainProblem->id,
            'internal_number' => (string) ($mainProblem->internal_number ?? ''),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ];

        $this->pushToUsers($reporterIds, 'reporter_email_own_problems', $title, $body, $data);
        $this->pushToUsers($watcherIds, 'reporter_email_watched_problems', $title, $body, $data);
    }

    private function pushToUsers(iterable $userIds, string $preferenceColumn, string $title, string $body, array $data): void
    {
        foreach ($userIds as $userId) {
            $user = User::query()
                ->notArchived()
                ->whereKey($userId)
                ->whereNotNull('push_fcm_token')
                ->where('email_notifications_enabled', true)
                ->where($preferenceColumn, true)
                ->first(['id', 'push_fcm_token']);

            if (! $user) {
                continue;
            }

            try {
                $user->notify(new PushNotification($title, $body, $data));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Push notification failed', [
                    'user_id' => $user->id,
                    'problem_id' => $data['problem_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
