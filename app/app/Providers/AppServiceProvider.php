<?php

namespace App\Providers;

use App\Models\EmailNotificationTemplate;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $appUrl = config('app.url');
        if (is_string($appUrl) && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        $this->guardDestructiveDatabaseCommands();
        $this->registerVerifyEmailFromTemplate();
        $this->registerPasswordResetFromTemplate();
    }

    private function guardDestructiveDatabaseCommands(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $allowDestructive = filter_var(env('DB_ALLOW_DESTRUCTIVE', false), FILTER_VALIDATE_BOOLEAN);

        DB::prohibitDestructiveCommands(! $allowDestructive);
    }

    private function registerVerifyEmailFromTemplate(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl): MailMessage {
            $displayName = trim(($notifiable->name ?? '').' '.($notifiable->surname ?? ''));
            if ($displayName === '') {
                $displayName = $notifiable->getEmailForVerification();
            }
            $appName = config('mail.brand_name');

            $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_VERIFY_EMAIL);
            if ($template && $template->is_enabled) {
                $map = [
                    '{user_name}' => $displayName,
                    '{app_name}' => $appName,
                ];
                $subject = str_replace(array_keys($map), array_values($map), $template->subject);
                $body = str_replace(array_keys($map), array_values($map), $template->body);

                $msg = (new MailMessage)
                    ->greeting('')
                    ->subject($subject)
                    ->salutation('Pagarbiai, '.$appName);

                foreach (array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $body))) as $line) {
                    $msg->line($line);
                }

                return $msg->action('Patvirtinti el. paštą', $verificationUrl);
            }

            return (new MailMessage)
                ->greeting('')
                ->subject('Patvirtinkite el. paštą – '.$appName)
                ->line('Sveiki, '.$displayName.'.')
                ->line('Patvirtinkite el. paštą, kad galėtumėte prisijungti.')
                ->action('Patvirtinti el. paštą', $verificationUrl)
                ->line('Jei paskyros nekūrėte, šį laišką ignoruokite.')
                ->salutation('Pagarbiai, '.$appName);
        });
    }

    private function registerPasswordResetFromTemplate(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            $expire = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            $displayName = trim(($notifiable->name ?? '').' '.($notifiable->surname ?? ''));
            if ($displayName === '') {
                $displayName = $notifiable->getEmailForPasswordReset();
            }
            $appName = config('mail.brand_name');

            $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_PASSWORD_RESET);
            if ($template && $template->is_enabled) {
                $map = [
                    '{user_name}' => $displayName,
                    '{app_name}' => $appName,
                    '{expire_minutes}' => (string) $expire,
                ];
                $subject = str_replace(array_keys($map), array_values($map), $template->subject);
                $body = str_replace(array_keys($map), array_values($map), $template->body);

                $msg = (new MailMessage)
                    ->greeting('')
                    ->subject($subject)
                    ->salutation('Pagarbiai, '.$appName);

                foreach (array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $body))) as $line) {
                    $msg->line($line);
                }

                return $msg->action('Nustatyti slaptažodį', $url);
            }

            return (new MailMessage)
                ->greeting('')
                ->subject('Slaptažodžio keitimas – '.$appName)
                ->line('Sveiki, '.$displayName.'.')
                ->line('Gavome prašymą pakeisti slaptažodį. Nuoroda galioja '.$expire.' min.')
                ->action('Nustatyti slaptažodį', $url)
                ->line('Jei prašymo nesiuntėte, šį laišką ignoruokite.')
                ->salutation('Pagarbiai, '.$appName);
        });
    }
}
