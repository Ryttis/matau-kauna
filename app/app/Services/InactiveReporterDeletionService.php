<?php

namespace App\Services;

use App\Models\EmailNotificationTemplate;
use App\Models\InactiveReporterDeletionSetting;
use App\Models\LoginLog;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Carbon;

class InactiveReporterDeletionService
{
    public function runRemindersAndDeletions(): array
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
                $this->deleteReporter($user);
                $deleted++;
            } elseif ($lastLogin->lt($reminderThreshold) && $user->inactive_reporter_reminder_sent_at === null) {
                $this->sendReminder($user, $setting);
                $user->update(['inactive_reporter_reminder_sent_at' => now()]);
                $reminded++;
            }
        }

        return ['reminded' => $reminded, 'deleted' => $deleted];
    }

    private function getReportersWithLastLogin(): \Illuminate\Database\Eloquent\Collection
    {
        $users = User::query()
            ->notArchived()
            ->whereHas('roles', fn ($q) => $q->where('role', UserRole::ROLE_REPORTER))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('role', [
                UserRole::ROLE_SYSTEM_ADMIN,
                UserRole::ROLE_PROBLEM_ADMIN,
                UserRole::ROLE_INSTITUTION_ADMIN,
                UserRole::ROLE_MUNICIPALITY_REP,
                UserRole::ROLE_EMPLOYEE,
            ]))
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

    private function sendReminder(User $user, InactiveReporterDeletionSetting $setting): void
    {
        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_INACTIVE_REPORTER_REMINDER);
        if (! $template || ! $template->is_enabled) {
            return;
        }
        $deletionDate = $user->last_login_at->copy()->addDays($setting->inactivity_days);
        $userName = trim($user->name.' '.($user->surname ?? ''));
        if ($userName === '') {
            $userName = $user->email;
        }
        $loginUrl = url(route('login', [], false));
        $replacements = [
            '{user_name}' => $userName,
            '{deletion_date}' => $deletionDate->format('Y-m-d'),
            '{inactivity_days}' => (string) $setting->inactivity_days,
            '{login_url}' => $loginUrl,
        ];
        $subject = str_replace(array_keys($replacements), array_values($replacements), $template->subject);
        $body = str_replace(array_keys($replacements), array_values($replacements), $template->body);
        if (filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            TransactionalMail::send($user->email, $subject, $body, $loginUrl, 'Prisijungti');
        }
    }

    private function deleteReporter(User $user): void
    {
        $user->roles()->detach();
        $user->delete();
    }
}
