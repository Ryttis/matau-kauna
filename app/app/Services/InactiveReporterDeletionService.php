<?php

namespace App\Services;

use App\Jobs\DeleteInactiveReporterJob;
use App\Jobs\SendInactiveReporterReminderJob;
use App\Models\EmailNotificationTemplate;
use App\Models\InactiveReporterDeletionSetting;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InactiveReporterDeletionService
{
    public function runRemindersAndDeletions(bool $dispatchToQueue = true): array
    {
        $setting = InactiveReporterDeletionSetting::get();
        if (! $setting || ! $setting->is_active) {
            return ['reminded' => 0, 'deleted' => 0];
        }

        $reminderDays = $setting->reminderDays();
        $inactivityDays = $setting->inactivity_days;
        $reminderThreshold = now()->subDays($reminderDays);
        $deletionThreshold = now()->subDays($inactivityDays);

        $reporters = $this->getReportersWithLastLogin();
        $reminded = 0;
        $deleted = 0;

        foreach ($reporters as $user) {
            $lastLogin = $user->last_login_at;
            if ($lastLogin === null) {
                continue;
            }
            if ($lastLogin->lt($deletionThreshold)) {
                if ($dispatchToQueue) {
                    DeleteInactiveReporterJob::dispatch($user->id, $inactivityDays);
                } else {
                    $this->deleteReporterById($user->id, $inactivityDays);
                }
                $deleted++;
            } elseif ($lastLogin->lt($reminderThreshold) && $user->inactive_reporter_reminder_sent_at === null) {
                if ($dispatchToQueue) {
                    SendInactiveReporterReminderJob::dispatch($user->id, $inactivityDays, $reminderDays);
                } else {
                    $this->sendReminderByUserId($user->id, $inactivityDays, $reminderDays);
                }
                $reminded++;
            }
        }

        return ['reminded' => $reminded, 'deleted' => $deleted];
    }

    private function getReportersWithLastLogin(): \Illuminate\Database\Eloquent\Collection
    {
        $users = User::query()
            ->notArchived()
            ->whereDoesntHave('roles.permissions', fn ($q) => $q->where('permission', 'issues.view'))
            ->get(['id', 'name', 'surname', 'email', 'last_login_at', 'inactive_reporter_reminder_sent_at']);

        foreach ($users as $user) {
            if ($user->last_login_at === null) {
                $fromLog = LoginLog::where('user_id', $user->id)
                    ->where('event_type', LoginLog::EVENT_LOGIN)
                    ->orderByDesc('created_at')
                    ->value('created_at');
                $user->setAttribute('last_login_at', $fromLog ? Carbon::parse($fromLog) : null);
            }
        }

        return $users;
    }

    public function sendReminderByUserId(int $userId, int $inactivityDays, int $reminderDays): void
    {
        $user = $this->findInactiveReporterById($userId);
        if (! $user || $user->inactive_reporter_reminder_sent_at !== null) {
            return;
        }

        if (! $user->last_login_at) {
            return;
        }
        $reminderThreshold = now()->subDays($reminderDays);
        $deletionThreshold = now()->subDays($inactivityDays);
        if (! $user->last_login_at->lt($reminderThreshold) || $user->last_login_at->lt($deletionThreshold)) {
            return;
        }

        $this->sendReminderByUser($user, $inactivityDays);
        $user->update(['inactive_reporter_reminder_sent_at' => now()]);
    }

    public function deleteReporterById(int $userId, int $inactivityDays): void
    {
        $user = $this->findInactiveReporterById($userId);
        if (! $user) {
            return;
        }
        if (! $user->last_login_at || ! $user->last_login_at->lt(now()->subDays($inactivityDays))) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $user->roles()->detach();
            $user->delete();
        });
    }

    private function findInactiveReporterById(int $userId): ?User
    {
        return User::query()
            ->notArchived()
            ->where('id', $userId)
            ->whereDoesntHave('roles.permissions', fn ($q) => $q->where('permission', 'issues.view'))
            ->first(['id', 'name', 'surname', 'email', 'last_login_at', 'inactive_reporter_reminder_sent_at']);
    }

    private function sendReminderByUser(User $user, int $inactivityDays): void
    {
        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_INACTIVE_REPORTER_REMINDER);
        if (! $template || ! $template->is_enabled) {
            return;
        }
        if (! $user->last_login_at) {
            return;
        }
        $deletionDate = $user->last_login_at->copy()->addDays($inactivityDays);
        $userName = trim($user->name.' '.($user->surname ?? ''));
        if ($userName === '') {
            $userName = $user->email;
        }
        $loginUrl = url(route('login', [], false));
        $replacements = [
            '{user_name}' => $userName,
            '{deletion_date}' => $deletionDate->format('Y-m-d'),
            '{inactivity_days}' => (string) $inactivityDays,
            '{login_url}' => $loginUrl,
        ];
        $subject = str_replace(array_keys($replacements), array_values($replacements), $template->subject);
        $body = str_replace(array_keys($replacements), array_values($replacements), $template->body);
        if (filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            TransactionalMail::send($user->email, $subject, $body, $loginUrl, 'Prisijungti');
        }
    }
}
