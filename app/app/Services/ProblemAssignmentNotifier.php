<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Mail;

class ProblemAssignmentNotifier
{
    public function notifyInstitutionAdminsOfAssignment(array $problemIds, int $institutionId): void
    {
        $problems = Problem::whereIn('id', $problemIds)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'title', 'created_at']);

        if ($problems->isEmpty()) {
            return;
        }

        $institution = Institution::find($institutionId);

        $admins = User::query()
            ->where('institution_id', $institutionId)
            ->where('email_updates_institution_admin', true)
            ->whereHas('roles', fn ($q) => $q->where('role', UserRole::ROLE_INSTITUTION_ADMIN))
            ->get(['email', 'name']);

        if ($admins->isEmpty()) {
            return;
        }

        $lines = [];
        $lines[] = 'Jums priskirtos naujos problemos:';
        foreach ($problems as $problem) {
            $lines[] = sprintf(
                '- %s | %s | %s | %s',
                $problem->internal_number,
                $problem->title,
                $problem->created_at?->format('Y-m-d'),
                route('problems.show', $problem->id)
            );
        }
        if ($institution) {
            $lines[] = '';
            $lines[] = 'Įstaiga: ' . $institution->name;
        }

        $subject = 'Naujos priskirtos problemos';
        $body = implode("\n", $lines);

        foreach ($admins as $admin) {
            Mail::raw($body, function ($message) use ($admin, $subject) {
                $message->to($admin->email)->subject($subject);
            });
        }
    }

    public function notifyInstitutionEmailOfAssignment(array $problemIds, int $institutionId): void
    {
        $institution = Institution::find($institutionId);

        if (! $institution) {
            return;
        }

        $email = $institution->integration_email ?: $institution->contact_email;
        if (! $email) {
            return;
        }

        $problems = Problem::whereIn('id', $problemIds)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'title', 'created_at']);

        if ($problems->isEmpty()) {
            return;
        }

        $lines = [];
        $lines[] = 'Priskirtų problemų sąrašas:';
        foreach ($problems as $problem) {
            $lines[] = sprintf(
                '- %s | %s | %s | %s',
                $problem->internal_number,
                $problem->title,
                $problem->created_at?->format('Y-m-d'),
                route('problems.show', $problem->id)
            );
        }
        $lines[] = '';
        $lines[] = 'Įstaiga: ' . $institution->name;

        $subject = 'Naujos priskirtos problemos';
        $body = implode("\n", $lines);

        Mail::raw($body, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }

    public function notifyEmployeeOfAssignment(Problem $problem, Employee $employee): void
    {
        $user = User::where('email', $employee->email)
            ->where('institution_id', $employee->institution_id)
            ->where('email_notifications_enabled', true)
            ->first(['email']);

        if (! $user) {
            return;
        }

        $subject = 'Jums priskirta nauja problema';
        $body = implode("\n", [
            'Jums priskirta nauja problema:',
            sprintf('%s | %s | %s', $problem->internal_number, $problem->title, route('problems.show', $problem->id)),
        ]);

        Mail::raw($body, function ($message) use ($user, $subject) {
            $message->to($user->email)->subject($subject);
        });
    }

    public function notifyDeadlineReminder(Problem $problem, string $type): void
    {
        $deadlineStr = $problem->completion_deadline?->format('Y-m-d') ?? '-';
        $subject = $type === 'overdue'
            ? "Problema {$problem->internal_number} – terminas praėjo"
            : "Problema {$problem->internal_number} – artėja terminas";
        $body = implode("\n", [
            $type === 'overdue' ? 'Ši problema turėjo būti išspręsta iki ' . $deadlineStr . '.' : 'Terminas išspręsti problemą: ' . $deadlineStr . '.',
            '',
            sprintf('%s | %s', $problem->internal_number, $problem->title),
            route('problems.show', $problem->id),
        ]);

        $recipients = [];
        if ($problem->assigned_employee_id) {
            $employee = $problem->assignedEmployee;
            if ($employee) {
                $user = User::where('email', $employee->email)
                    ->where('institution_id', $employee->institution_id)
                    ->where('email_notifications_enabled', true)
                    ->first(['email']);
                if ($user) {
                    $recipients[] = $user->email;
                }
            }
        }
        if ($problem->institution_id) {
            $admins = User::query()
                ->where('institution_id', $problem->institution_id)
                ->where('email_updates_institution_admin', true)
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
