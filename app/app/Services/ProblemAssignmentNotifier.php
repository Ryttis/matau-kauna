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
}
