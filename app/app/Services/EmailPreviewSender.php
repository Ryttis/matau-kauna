<?php

namespace App\Services;

use App\Enums\ProblemStatus;
use App\Models\EmailNotificationTemplate;
use App\Models\Institution;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\AccountCreatedNotification;
use App\Notifications\InvitationNotification;
use App\Support\EmailBody;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class EmailPreviewSender
{
    public static function kinds(): array
    {
        return [
            EmailNotificationTemplate::KEY_VERIFY_EMAIL,
            EmailNotificationTemplate::KEY_PASSWORD_RESET,
            EmailNotificationTemplate::KEY_INSTITUTION_ASSIGNMENT,
            EmailNotificationTemplate::KEY_INSTITUTION_INVITATION,
            EmailNotificationTemplate::KEY_MANUAL_ASSIGN_TO_INSTITUTION_EMAIL,
            EmailNotificationTemplate::KEY_EMPLOYEE_ASSIGNMENT,
            EmailNotificationTemplate::KEY_DEADLINE_APPROACHING,
            EmailNotificationTemplate::KEY_DEADLINE_OVERDUE,
            EmailNotificationTemplate::KEY_INACTIVE_REPORTER_REMINDER,
            EmailNotificationTemplate::KEY_REPORTER_STATUS_CHANGE,
            EmailNotificationTemplate::KEY_REPORTER_REVIEW_ENTRY,
            EmailNotificationTemplate::KEY_INSTITUTION_PROBLEM_SHARE,
            EmailNotificationTemplate::KEY_ACCOUNT_CREATED,
        ];
    }

    public function sendAll(string $recipient): void
    {
        foreach (self::kinds() as $kind) {
            $this->sendKind($kind, $recipient);
        }
    }

    public function sendKind(string $kind, string $recipient): void
    {
        match ($kind) {
            EmailNotificationTemplate::KEY_VERIFY_EMAIL => $this->sendVerifyEmailPreview($recipient),
            EmailNotificationTemplate::KEY_PASSWORD_RESET => $this->sendPasswordResetPreview($recipient),
            EmailNotificationTemplate::KEY_INSTITUTION_ASSIGNMENT => $this->sendInstitutionAssignmentPreview($recipient),
            EmailNotificationTemplate::KEY_INSTITUTION_INVITATION => $this->sendInvitationPreview($recipient),
            EmailNotificationTemplate::KEY_MANUAL_ASSIGN_TO_INSTITUTION_EMAIL => $this->sendManualAssignPreview($recipient),
            EmailNotificationTemplate::KEY_EMPLOYEE_ASSIGNMENT => $this->sendEmployeeAssignmentPreview($recipient),
            EmailNotificationTemplate::KEY_DEADLINE_APPROACHING => $this->sendDeadlinePreview($recipient, 'approaching'),
            EmailNotificationTemplate::KEY_DEADLINE_OVERDUE => $this->sendDeadlinePreview($recipient, 'overdue'),
            EmailNotificationTemplate::KEY_INACTIVE_REPORTER_REMINDER => $this->sendInactiveReporterPreview($recipient),
            EmailNotificationTemplate::KEY_REPORTER_STATUS_CHANGE => $this->sendReporterStatusPreview($recipient),
            EmailNotificationTemplate::KEY_REPORTER_REVIEW_ENTRY => $this->sendReporterReviewPreview($recipient),
            EmailNotificationTemplate::KEY_INSTITUTION_PROBLEM_SHARE => $this->sendInstitutionSharePreview($recipient),
            EmailNotificationTemplate::KEY_ACCOUNT_CREATED => $this->sendAccountCreatedPreview($recipient),
            default => throw new InvalidArgumentException('Unknown preview kind: '.$kind),
        };
    }

    private function sendVerifyEmailPreview(string $recipient): void
    {
        Notification::send($this->previewUser($recipient), new VerifyEmail);
    }

    private function sendPasswordResetPreview(string $recipient): void
    {
        Notification::send($this->previewUser($recipient), new ResetPassword('demo-token-not-valid'));
    }

    private function sendInstitutionAssignmentPreview(string $recipient): void
    {
        $key = EmailNotificationTemplate::KEY_INSTITUTION_ASSIGNMENT;
        $u = $this->sampleProblemUrl();
        $list = '- KP-2026-000099 | Duobė gatvėje | 2026-03-28 | '.$u;
        $map = [
            'problem_list' => $list,
            'institution_name' => 'Demo savivaldybės įstaiga',
        ];
        $fbSubject = 'Naujos priskirtos problemos';
        $fbBody = "Jums priskirtos naujos problemos:\n{problem_list}\n\nĮstaiga: {institution_name}";
        $r = $this->renderFromTemplateOrFallback($key, $map, $fbSubject, $fbBody);
        TransactionalMail::send($recipient, $r['subject'], $r['body'], $u, 'Atidaryti problemą');
    }

    private function sendInvitationPreview(string $recipient): void
    {
        $invitation = Invitation::make([
            'email' => $recipient,
            'token' => 'preview-invite-token',
            'expires_at' => now()->addDays(7),
        ]);
        $invitation->setRelation('institution', new Institution(['name' => 'Demo savivaldybės įstaiga']));
        $invitation->setRelation('role', new UserRole(['name' => 'Įstaigos administratorius']));

        Notification::route('mail', $recipient)->notify(new InvitationNotification($invitation));
    }

    private function sendManualAssignPreview(string $recipient): void
    {
        $key = EmailNotificationTemplate::KEY_MANUAL_ASSIGN_TO_INSTITUTION_EMAIL;
        $u = $this->sampleProblemUrl();
        $list = '- KP-2026-000099 | Duobė gatvėje | '.$u.' | Terminas: 2026-04-15';
        $map = [
            'institution_name' => 'Demo savivaldybės įstaiga',
            'problem_list' => $list,
            'internal_number' => 'KP-2026-000099',
            'description_excerpt' => 'Duobė gatvėje, reikia užtaisyti.',
            'url' => $u,
            'deadline_date' => '2026-04-15',
            'title' => 'Duobė gatvėje, reikia užtaisyti.',
        ];
        $fbSubject = 'Problemų priskyrimo informacija';
        $fbBody = "Priskirtų problemų sąrašas:\n\n{problem_list}\n\nPriskirta įstaiga: {institution_name}";
        $r = $this->renderFromTemplateOrFallback($key, $map, $fbSubject, $fbBody);
        TransactionalMail::send($recipient, $r['subject'], $r['body'], $u, 'Atidaryti problemą');
    }

    private function sendEmployeeAssignmentPreview(string $recipient): void
    {
        $key = EmailNotificationTemplate::KEY_EMPLOYEE_ASSIGNMENT;
        $u = $this->sampleProblemUrl();
        $excerpt = 'Duobė gatvėje, reikia užtaisyti.';
        $map = [
            'internal_number' => 'KP-2026-000099',
            'title' => $excerpt,
            'description_excerpt' => $excerpt,
            'url' => $u,
        ];
        $fbSubject = 'Jums priskirta nauja problema';
        $fbBody = "Jums priskirta nauja problema:\n{internal_number} | {description_excerpt} | {url}";
        $r = $this->renderFromTemplateOrFallback($key, $map, $fbSubject, $fbBody);
        TransactionalMail::send($recipient, $r['subject'], $r['body'], $u, 'Atidaryti problemą');
    }

    private function sendDeadlinePreview(string $recipient, string $variant): void
    {
        $key = $variant === 'overdue'
            ? EmailNotificationTemplate::KEY_DEADLINE_OVERDUE
            : EmailNotificationTemplate::KEY_DEADLINE_APPROACHING;
        $u = $this->sampleProblemUrl();
        $excerpt = 'Šaligatvio plytelės sulūžusios.';
        $deadline = '2026-04-10';
        $map = [
            'internal_number' => 'KP-2026-000088',
            'title' => $excerpt,
            'description_excerpt' => $excerpt,
            'url' => $u,
            'deadline_date' => $deadline,
        ];
        if ($variant === 'overdue') {
            $fbSubject = 'Problema {internal_number} – terminas praėjo';
            $fbBody = "Ši problema turėjo būti išspręsta iki {deadline_date}.\n\n{internal_number} | {description_excerpt}\n{url}";
        } else {
            $fbSubject = 'Problema {internal_number} – artėja terminas';
            $fbBody = "Terminas išspręsti problemą: {deadline_date}.\n\n{internal_number} | {description_excerpt}\n{url}";
        }
        $r = $this->renderFromTemplateOrFallback($key, $map, $fbSubject, $fbBody);
        TransactionalMail::send($recipient, $r['subject'], $r['body'], $u, 'Atidaryti problemą');
    }

    private function sendInactiveReporterPreview(string $recipient): void
    {
        $key = EmailNotificationTemplate::KEY_INACTIVE_REPORTER_REMINDER;
        $login = url(route('login', [], false));
        $map = [
            'user_name' => 'Jonas Peržiūra',
            'deletion_date' => '2026-12-31',
            'inactivity_days' => '365',
            'login_url' => $login,
        ];
        $fbSubject = 'Jūsų paskyra bus ištrinta dėl neaktyvumo';
        $fbBody = "Sveiki, {user_name}.\n\nJūsų paskyra nebuvo naudojama daugiau nei {inactivity_days} dienų. Paskyra bus automatiškai ištrinta {deletion_date}, jei neprisijungsite.\n\nPrisijunkite: {login_url}";
        $r = $this->renderFromTemplateOrFallback($key, $map, $fbSubject, $fbBody);
        TransactionalMail::send($recipient, $r['subject'], $r['body'], $login, 'Prisijungti');
    }

    private function sendReporterStatusPreview(string $recipient): void
    {
        $key = EmailNotificationTemplate::KEY_REPORTER_STATUS_CHANGE;
        $u = $this->sampleReportUrl();
        $excerpt = 'Apšvietimas kieme neveikia.';
        $old = ProblemStatus::NEW->value;
        $new = ProblemStatus::IN_REVIEW->value;
        $map = [
            'internal_number' => 'KP-2026-000077',
            'description_excerpt' => $excerpt,
            'url' => $u,
            'old_status' => $old,
            'new_status' => $new,
            'old_status_label' => ProblemStatus::NEW->label(),
            'new_status_label' => ProblemStatus::IN_REVIEW->label(),
        ];
        $fbSubject = 'Problema {internal_number}: būsena pakeista į „{new_status_label}“';
        $fbBody = "Sveiki,\n\nProblemai {internal_number} pakeista būsena.\n\nAnkstesnė būsena: {old_status_label}\nNauja būsena: {new_status_label}\n\nAprašymo fragmentas: {description_excerpt}\n\nPeržiūrėti: {url}";
        $r = $this->renderFromTemplateOrFallback($key, $map, $fbSubject, $fbBody);
        TransactionalMail::send($recipient, $r['subject'], $r['body'], $u, 'Peržiūrėti problemą');
    }

    private function sendReporterReviewPreview(string $recipient): void
    {
        $key = EmailNotificationTemplate::KEY_REPORTER_REVIEW_ENTRY;
        $u = $this->sampleReportUrl();
        $excerpt = 'Aplinkos tvarkymas parkelyje.';
        $map = [
            'internal_number' => 'KP-2026-000066',
            'description_excerpt' => $excerpt,
            'url' => $u,
            'status_label' => ProblemStatus::IN_REVIEW->label(),
            'responsible' => 'Vardenis Pavardenis',
            'comment_excerpt' => 'Vykdoma teritorijos apžiūra, terminas – iki savaitės galo.',
            'entry_date' => '2026-03-28 10:30',
        ];
        $fbSubject = 'Problema {internal_number}: naujas nagrinėjimo įrašas';
        $fbBody = "Sveiki,\n\nProblemai {internal_number} pridėtas naujas viešas nagrinėjimo įrašas.\n\nBūsena: {status_label}\nAtsakingas: {responsible}\nData: {entry_date}\n\nKomentaras: {comment_excerpt}\n\nPeržiūrėti: {url}";
        $r = $this->renderFromTemplateOrFallback($key, $map, $fbSubject, $fbBody);
        TransactionalMail::send($recipient, $r['subject'], $r['body'], $u, 'Peržiūrėti problemą');
    }

    private function sendInstitutionSharePreview(string $recipient): void
    {
        $key = EmailNotificationTemplate::KEY_INSTITUTION_PROBLEM_SHARE;
        $u = $this->sampleProblemUrl();
        $map = [
            'internal_number' => 'KP-2026-000055',
            'description_excerpt' => 'Informacija apie problemą, trumpai.',
            'address' => 'Vilniaus g. 1, Kaunas',
            'created_at' => '2026-03-20',
            'deadline_line' => 'Atlikimo terminas: 2026-04-01',
            'url' => $u,
        ];
        $fbSubject = 'Problemos priskyrimo informacija – {internal_number}';
        $fbBody = "Nr: {internal_number}\nAprašymas: {description_excerpt}\nAdresas: {address}\nSukūrimo data: {created_at}\n{deadline_line}";
        $r = $this->renderFromTemplateOrFallback($key, $map, $fbSubject, $fbBody);
        TransactionalMail::send($recipient, $r['subject'], $r['body'], $u, 'Atidaryti problemą');
    }

    private function sendAccountCreatedPreview(string $recipient): void
    {
        $user = $this->previewUser($recipient);
        Notification::send($user, new AccountCreatedNotification);
    }

    private function renderFromTemplateOrFallback(string $key, array $map, string $fallbackSubject, string $fallbackBody): array
    {
        $template = EmailNotificationTemplate::getByKey($key);
        $subjectTpl = $template?->subject ?? $fallbackSubject;
        $bodyTpl = $template?->body ?? $fallbackBody;

        return [
            'subject' => EmailBody::replacePlaceholders($subjectTpl, $map),
            'body' => EmailBody::replacePlaceholders($bodyTpl, $map),
        ];
    }

    private function previewUser(string $email): User
    {
        $user = User::make([
            'name' => 'Jonas',
            'surname' => 'Peržiūra',
            'email' => $email,
        ]);
        $user->id = 1;

        return $user;
    }

    private function sampleProblemUrl(): string
    {
        return url(route('problems.show', ['problem' => 1], false));
    }

    private function sampleReportUrl(): string
    {
        return url(route('reports.show', ['problem' => 1], false));
    }
}
