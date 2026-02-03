<?php

namespace App\Notifications;

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
        $url = url('/register/invite/'.$this->invitation->token);

        return (new MailMessage)
            ->subject('Pakvietimas prisijungti prie Kauno problemų sistemos')
            ->line('Jūsų pakviesta prisijungti prie sistemos kaip įstaigos naudotojas.')
            ->action('Sukurti paskyrą', $url)
            ->line('Nuoroda galioja iki '.$this->invitation->expires_at->format('Y-m-d H:i').'.');
    }
}
