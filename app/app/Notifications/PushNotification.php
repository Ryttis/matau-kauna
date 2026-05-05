<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotificationResource;

class PushNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $title,
        private string $body,
        private array $data = [],
    ) {}

    public function via(object $notifiable): array
    {
        return method_exists($notifiable, 'routeNotificationForFcm') && $notifiable->routeNotificationForFcm()
            ? [FcmChannel::class]
            : [];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(notification: new FcmNotificationResource(
            title: $this->title,
            body: $this->body,
        )))->data(array_map('strval', $this->data));
    }
}
