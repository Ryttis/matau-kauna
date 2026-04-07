<?php

namespace App\Notifications;

use App\Models\EmailNotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreatedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        if (! EmailNotificationTemplate::isEnabled(EmailNotificationTemplate::KEY_ACCOUNT_CREATED)) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('mail.brand_name');
        $loginUrl = url('/login');
        $userName = $notifiable->name ?: $notifiable->email;

        $map = [
            '{app_name}' => $appName,
            '{user_name}' => $userName,
            '{user_email}' => $notifiable->email,
            '{url}' => $loginUrl,
        ];

        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_ACCOUNT_CREATED);

        if ($template) {
            $subject = str_replace(array_keys($map), array_values($map), $template->subject);
            $body = str_replace(array_keys($map), array_values($map), $template->body);

            $msg = (new MailMessage)
                ->greeting('')
                ->subject($subject)
                ->salutation('Pagarbiai, '.$appName);

            foreach (array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $body))) as $line) {
                $msg->line($line);
            }

            return $msg->action('Prisijungti', $loginUrl);
        }

        return (new MailMessage)
            ->greeting('')
            ->subject('Jūsų paskyra sukurta – '.$appName)
            ->line('Sveiki, '.$userName.'!')
            ->line('Jūsų paskyra sistemoje '.$appName.' buvo sukurta administratoriaus.')
            ->line('Prisijungimo el. paštas: '.$notifiable->email)
            ->action('Prisijungti', $loginUrl)
            ->line('Prisijunkite ir, jei reikia, pasikeiskite slaptažodį.')
            ->salutation('Pagarbiai, '.$appName);
    }
}
