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
        $this->notifyInstitutionOfAssignment($problemIds, $institutionId);
    }

    public function notifyInstitutionEmailOfAssignment(array $problemIds, int $institutionId): void
    {
        $this->notifyInstitutionOfAssignment($problemIds, $institutionId);
    }

    public function notifyInstitutionOfAssignment(array $problemIds, int $institutionId): void
    {
        $problems = Problem::whereIn('id', $problemIds)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'title', 'created_at']);

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
