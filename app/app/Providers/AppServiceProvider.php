<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new MailMessage)
                ->subject('Slaptažodžio keitimas')
                ->greeting('Sveiki,')
                ->line('Gavome jūsų slaptažodžio keitimo užklausą. Norėdami nustatyti naują slaptažodį, paspauskite mygtuką žemiau.')
                ->action('Keisti slaptažodį', $url)
                ->line('Nuoroda galioja '.$expire.' min. Jei slaptažodžio nekeitėte, šį laišką galite ignoruoti.');
        });
    }
}
