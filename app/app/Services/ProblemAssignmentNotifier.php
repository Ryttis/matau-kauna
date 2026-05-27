<?php

namespace App\Services;

use App\Models\EmailNotificationTemplate;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use App\Support\EmailBody;
use Illuminate\Support\Collection;

class ProblemAssignmentNotifier
{
    public function buildInstitutionProblemShareEmail(Problem $problem): array
    {
        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_INSTITUTION_PROBLEM_SHARE);
        $map = $this->problemPlaceholderMap($problem, 500);
        $url = $map['url'];

        if ($template && $template->is_enabled) {
            return [
                'subject' => EmailBody::replacePlaceholders($template->subject, $map),
                'body' => EmailBody::replacePlaceholders($template->body, $map),
                'action_url' => $url,
                'action_label' => 'Atidaryti problemą',
            ];
        }

        $lines = [
            'Problemos informacija:',
            'Nr: '.$map['internal_number'],
            'Pareiškėjas: '.$map['reporter_name'],
            'Pareiškėjo el. paštas: '.$map['reporter_email'],
            'Pareiškėjo tel. nr.: '.$map['reporter_phone'],
            'Kategorija: '.$map['category'],
            'Pobūdis: '.$map['nature'],
            'Adresas: '.$map['address'],
            'Aprašymas: '.$map['description'],
            'Sukūrimo data: '.$map['created_at'],
        ];
        if ($map['deadline_line'] !== '') {
            $lines[] = $map['deadline_line'];
        }
        $lines[] = 'Nuotraukos:';
        $lines[] = $map['photo_links'];

        return [
            'subject' => 'Problemos priskyrimo informacija – '.$map['internal_number'],
            'body' => implode("\n", $lines),
            'action_url' => $url,
            'action_label' => 'Atidaryti problemą',
        ];
    }

    public function buildManualAssignToInstitutionEmail(Institution $institution, Collection $problems): array
    {
        $first = $problems->first();
        $firstMap = $first ? $this->problemPlaceholderMap($first, 200) : [];
        $urlFirst = $firstMap['url'] ?? '';
        $problemList = $problems
            ->map(fn (Problem $problem) => $this->problemDetailsBlock($problem))
            ->implode("\n\n---\n\n");

        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_MANUAL_ASSIGN_TO_INSTITUTION_EMAIL);
        if ($template && $template->is_enabled) {
            $map = array_merge($firstMap, [
                'institution_name' => $institution->name,
                'problem_list' => $problemList,
                'internal_number' => $first?->internal_number ?? '',
                'description_excerpt' => $firstMap['description_excerpt'] ?? '',
                'url' => $urlFirst,
                'deadline_date' => $firstMap['deadline_date'] ?? '-',
                'title' => $firstMap['description_excerpt'] ?? '',
            ]);

            return [
                'subject' => EmailBody::replacePlaceholders($template->subject, $map),
                'body' => EmailBody::replacePlaceholders($template->body, $map),
                'action_url' => $urlFirst,
                'action_label' => 'Atidaryti problemą',
            ];
        }

        $lines = [
            'Priskirtų problemų sąrašas:',
            '',
            $problemList,
            '',
            'Priskirta įstaiga: '.$institution->name,
        ];

        return [
            'subject' => 'Problemų priskyrimo informacija',
            'body' => implode("\n", $lines),
            'action_url' => $urlFirst,
            'action_label' => 'Atidaryti problemą',
        ];
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
                $problem->created_at?->timezone('Europe/Vilnius')->format('Y-m-d'),
                route('problems.show', $problem->id)
            );
        }
        $problemList = implode("\n", $problemLines);
        $institutionName = $institution ? $institution->name : '';

        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_INSTITUTION_ASSIGNMENT);
        if ($template && $template->is_enabled) {
            $subject = EmailBody::replacePlaceholders($template->subject, [
                'problem_list' => $problemList,
                'institution_name' => $institutionName,
            ]);
            $body = EmailBody::replacePlaceholders($template->body, [
                'problem_list' => $problemList,
                'institution_name' => $institutionName,
            ]);
        } else {
            $subject = 'Naujos priskirtos problemos';
            $body = "Jums priskirtos naujos problemos:\n{$problemList}\n\nĮstaiga: {$institutionName}";
        }

        $firstProblem = $problems->first();
        $actionUrl = $firstProblem ? route('problems.show', $firstProblem->id) : null;

        foreach (array_values($recipientEmails) as $email) {
            TransactionalMail::send($email, $subject, $body, $actionUrl, 'Atidaryti problemą');
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
            $subject = EmailBody::replacePlaceholders($template->subject, [
                'internal_number' => $problem->internal_number,
                'title' => $excerpt,
                'description_excerpt' => $excerpt,
                'url' => $url,
            ]);
            $body = EmailBody::replacePlaceholders($template->body, [
                'internal_number' => $problem->internal_number,
                'title' => $excerpt,
                'description_excerpt' => $excerpt,
                'url' => $url,
            ]);
        } else {
            $subject = 'Jums priskirta nauja problema';
            $body = "Jums priskirta nauja problema:\n{$problem->internal_number} | {$excerpt} | {$url}";
        }

        TransactionalMail::send($user->email, $subject, $body, $url, 'Atidaryti problemą');
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
            $subject = EmailBody::replacePlaceholders($template->subject, [
                'internal_number' => $problem->internal_number,
                'title' => $excerpt,
                'description_excerpt' => $excerpt,
                'url' => $url,
                'deadline_date' => $deadlineStr,
            ]);
            $body = EmailBody::replacePlaceholders($template->body, [
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
                $this->appendEmployeeDeadlineRecipient($employee, $recipients);
            }
        }
        if ($problem->assigned_institution_team_id) {
            $problem->loadMissing('assignedInstitutionTeam.employees');
            foreach ($problem->assignedInstitutionTeam?->employees ?? [] as $employee) {
                $this->appendEmployeeDeadlineRecipient($employee, $recipients);
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
        foreach (array_unique($recipients) as $email) {
            TransactionalMail::send($email, $subject, $body, $url, 'Atidaryti problemą');
        }
    }

    private function appendEmployeeDeadlineRecipient(Employee $employee, array &$recipients): void
    {
        $user = User::query()
            ->notArchived()
            ->where('email', $employee->email)
            ->where('institution_id', $employee->institution_id)
            ->wantsEmployeeAssignmentEmails()
            ->whereHas('roles', fn ($q) => $q->whereIn('role', UserRole::staffRoles()))
            ->first(['email']);
        if ($user) {
            $recipients[] = $user->email;
        }
    }

    private function problemPlaceholderMap(Problem $problem, int $excerptLimit): array
    {
        $problem->loadMissing([
            'reportedByUser:id,name,surname,email,phone',
            'problemType:id,name',
            'problemNature:id,name',
            'attachments:id,problem_id,path,original_name,mime_type,kind',
        ]);

        $deadlineDate = $problem->completion_deadline?->format('Y-m-d') ?? '-';
        $deadlineLine = $problem->completion_deadline
            ? 'Atlikimo terminas: '.$deadlineDate
            : '';
        $photoLinks = $this->photoLinks($problem);

        return [
            'internal_number' => $problem->internal_number ?? '-',
            'description_excerpt' => Problem::descriptionPreview($problem->description, $excerptLimit) ?: '-',
            'description' => $this->plainDescription($problem),
            'address' => $problem->address ?? '-',
            'created_at' => $problem->created_at?->timezone('Europe/Vilnius')->format('Y-m-d') ?? '-',
            'deadline_line' => $deadlineLine,
            'deadline_date' => $deadlineDate,
            'url' => route('problems.show', $problem->id),
            'reporter_name' => $this->reporterName($problem),
            'reporter_email' => $problem->reportedByUser?->email ?: '-',
            'reporter_phone' => $problem->reportedByUser?->phone ?: '-',
            'category' => $problem->problemType?->name ?: ($problem->category ?: '-'),
            'nature' => $problem->problemNature?->name ?: '-',
            'photo_links' => $photoLinks,
            'photos' => $photoLinks,
        ];
    }

    private function problemDetailsBlock(Problem $problem): string
    {
        $map = $this->problemPlaceholderMap($problem, 500);
        $lines = [
            'Nr: '.$map['internal_number'],
            'Pareiškėjas: '.$map['reporter_name'],
            'Pareiškėjo el. paštas: '.$map['reporter_email'],
            'Pareiškėjo tel. nr.: '.$map['reporter_phone'],
            'Kategorija: '.$map['category'],
            'Pobūdis: '.$map['nature'],
            'Adresas: '.$map['address'],
            'Aprašymas: '.$map['description'],
            'Sukūrimo data: '.$map['created_at'],
        ];

        if ($map['deadline_line'] !== '') {
            $lines[] = $map['deadline_line'];
        }

        $lines[] = 'Nuotraukos:';
        $lines[] = $map['photo_links'];
        $lines[] = 'Nuoroda: '.$map['url'];

        return implode("\n", $lines);
    }

    private function reporterName(Problem $problem): string
    {
        $user = $problem->reportedByUser;
        if (! $user) {
            return '-';
        }

        $name = trim(implode(' ', array_filter([
            $user->name,
            $user->surname,
        ])));

        return $name !== '' ? $name : '-';
    }

    private function plainDescription(Problem $problem): string
    {
        $description = trim(strip_tags((string) $problem->description));

        return $description !== '' ? $description : '-';
    }

    private function photoLinks(Problem $problem): string
    {
        $links = $problem->attachments
            ->filter(fn ($attachment) => $attachment->kind === 'photo' || str_starts_with((string) $attachment->mime_type, 'image/'))
            ->values()
            ->map(fn ($attachment, int $index) => sprintf('[Nuotrauka %d](%s)', $index + 1, url($attachment->publicUrl())));

        return $links->isEmpty() ? '-' : $links->implode("\n");
    }
}
