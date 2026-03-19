<?php

namespace App\Services;

use App\Models\EmailNotificationTemplate;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Mail;

class ProblemAssignmentNotifier
{
    private function replacePlaceholders(string $text, array $replacements): string
    {
        foreach ($replacements as $placeholder => $value) {
            $text = str_replace('{'.$placeholder.'}', (string) $value, $text);
        }

        return $text;
    }

    public function notifyInstitutionOfAssignment(array $problemIds, int $institutionId): void
    {
        $problems = Problem::whereIn('id', $problemIds)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'description', 'created_at']);

        if ($problems->isEmpty()) {
            return;
        }

        $institution = Institution::find($institutionId);

        $recipientEmails = [];
        $admins = User::query()
            ->notArchived()
            ->where('institution_id', $institutionId)
            ->wantsInstitutionAdminAssignmentEmails()
            ->whereHas('roles', fn ($q) => $q->where('role', UserRole::ROLE_INSTITUTION_ADMIN))
            ->pluck('email')
            ->all();
        foreach ($admins as $email) {
            if ($email !== null && $email !== '') {
                $recipientEmails[strtolower(trim($email))] = trim($email);
            }
        }

        if (empty($recipientEmails)) {
            return;
        }

        $problemLines = [];
        foreach ($problems as $problem) {
            $problemLines[] = sprintf(
                '- %s | %s | %s | %s',
                $problem->internal_number,
                Problem::descriptionPreview($problem->description, 120),
                $problem->created_at?->format('Y-m-d'),
                route('problems.show', $problem->id)
            );
        }
        $problemList = implode("\n", $problemLines);
        $institutionName = $institution ? $institution->name : '';

        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_INSTITUTION_ASSIGNMENT);
        if ($template && $template->is_enabled) {
            $subject = $this->replacePlaceholders($template->subject, [
                'problem_list' => $problemList,
                'institution_name' => $institutionName,
            ]);
            $body = $this->replacePlaceholders($template->body, [
                'problem_list' => $problemList,
                'institution_name' => $institutionName,
            ]);
        } else {
            $subject = 'Naujos priskirtos problemos';
            $body = "Jums priskirtos naujos problemos:\n{$problemList}\n\nĮstaiga: {$institutionName}";
        }

        foreach (array_values($recipientEmails) as $email) {
            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });
        }
    }

    public function notifyEmployeeOfAssignment(Problem $problem, Employee $employee): void
    {
        $user = User::query()
            ->notArchived()
            ->where('email', $employee->email)
            ->where('institution_id', $employee->institution_id)
            ->wantsEmployeeAssignmentEmails()
            ->first(['email']);

        if (! $user) {
            return;
        }

        $url = route('problems.show', $problem->id);
        $excerpt = Problem::descriptionPreview($problem->description, 200);
        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_EMPLOYEE_ASSIGNMENT);
        if ($template && $template->is_enabled) {
            $subject = $this->replacePlaceholders($template->subject, [
                'internal_number' => $problem->internal_number,
                'title' => $excerpt,
                'description_excerpt' => $excerpt,
                'url' => $url,
            ]);
            $body = $this->replacePlaceholders($template->body, [
                'internal_number' => $problem->internal_number,
                'title' => $excerpt,
                'description_excerpt' => $excerpt,
                'url' => $url,
            ]);
        } else {
            $subject = 'Jums priskirta nauja problema';
            $body = "Jums priskirta nauja problema:\n{$problem->internal_number} | {$excerpt} | {$url}";
        }

        Mail::raw($body, function ($message) use ($user, $subject) {
            $message->to($user->email)->subject($subject);
        });
    }

    public function notifyDeadlineReminder(Problem $problem, string $type): void
    {
        $key = $type === 'overdue'
            ? EmailNotificationTemplate::KEY_DEADLINE_OVERDUE
            : EmailNotificationTemplate::KEY_DEADLINE_APPROACHING;

        $template = EmailNotificationTemplate::getByKey($key);
        if ($template && ! $template->is_enabled) {
            return;
        }

        $deadlineStr = $problem->completion_deadline?->format('Y-m-d') ?? '-';
        $url = route('problems.show', $problem->id);
        $excerpt = Problem::descriptionPreview($problem->description, 200);

        if ($template) {
            $subject = $this->replacePlaceholders($template->subject, [
                'internal_number' => $problem->internal_number,
                'title' => $excerpt,
                'description_excerpt' => $excerpt,
                'url' => $url,
                'deadline_date' => $deadlineStr,
            ]);
            $body = $this->replacePlaceholders($template->body, [
                'internal_number' => $problem->internal_number,
                'title' => $excerpt,
                'description_excerpt' => $excerpt,
                'url' => $url,
                'deadline_date' => $deadlineStr,
            ]);
        } else {
            $subject = $type === 'overdue'
                ? "Problema {$problem->internal_number} – terminas praėjo"
                : "Problema {$problem->internal_number} – artėja terminas";
            $body = implode("\n", [
                $type === 'overdue' ? 'Ši problema turėjo būti išspręsta iki '.$deadlineStr.'.' : 'Terminas išspręsti problemą: '.$deadlineStr.'.',
                '',
                $problem->internal_number.' | '.$excerpt,
                $url,
            ]);
        }

        $recipients = [];
        if ($problem->assigned_employee_id) {
            $employee = $problem->assignedEmployee;
            if ($employee) {
                $user = User::query()
                    ->notArchived()
                    ->where('email', $employee->email)
                    ->where('institution_id', $employee->institution_id)
                    ->wantsEmployeeAssignmentEmails()
                    ->first(['email']);
                if ($user) {
                    $recipients[] = $user->email;
                }
            }
        }
        if ($problem->institution_id) {
            $admins = User::query()
                ->notArchived()
                ->where('institution_id', $problem->institution_id)
                ->wantsInstitutionAdminAssignmentEmails()
                ->whereHas('roles', fn ($q) => $q->where('role', UserRole::ROLE_INSTITUTION_ADMIN))
                ->pluck('email')
                ->all();
            $recipients = array_unique(array_merge($recipients, $admins));
        }
        foreach ($recipients as $email) {
            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });
        }
    }
}
