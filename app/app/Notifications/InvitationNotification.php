<?php

namespace App\Notifications;

use App\Models\EmailNotificationTemplate;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Invitation $invitation
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->invitation->loadMissing(['institution:id,name', 'role:id,name']);

        $url = url('/register/invite/'.$this->invitation->token);
        $appName = config('mail.brand_name');
        $institutionName = $this->invitation->institution?->name ?? '-';
        $roleName = $this->invitation->role?->name ?? '-';
        $expiresAt = $this->invitation->expires_at?->timezone('Europe/Vilnius')->format('Y-m-d H:i') ?? '-';

        $map = [
            '{app_name}' => $appName,
            '{institution_name}' => $institutionName,
            '{role_name}' => $roleName,
            '{expires_at}' => $expiresAt,
            '{url}' => $url,
        ];

        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_INSTITUTION_INVITATION);
        if ($template && $template->is_enabled) {
            $subject = str_replace(array_keys($map), array_values($map), $template->subject);
            $body = str_replace(array_keys($map), array_values($map), $template->body);

            $msg = (new MailMessage)
                ->greeting('')
                ->subject($subject)
                ->salutation('Pagarbiai, '.$appName);

            foreach (array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $body))) as $line) {
                $msg->line($line);
            }

            return $msg->action('Sukurti paskyrą', $url);
        }

        return (new MailMessage)
            ->greeting('')
            ->subject('Pakvietimas į '.$appName)
            ->line('Jūs pakviesti prisijungti prie sistemos kaip įstaigos naudotojas.')
            ->action('Sukurti paskyrą', $url)
            ->line('Nuoroda galioja iki '.$expiresAt.'.')
            ->salutation('Pagarbiai, '.$appName);
    }
}
