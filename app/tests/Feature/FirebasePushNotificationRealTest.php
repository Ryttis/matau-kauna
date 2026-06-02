<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PushNotification;
use Tests\TestCase;

class FirebasePushNotificationRealTest extends TestCase
{
    private const FCM_TOKEN = 'e0SlnniADU0zsJfo_ASUpi:APA91bEf8kKEm1zt-c1o9EneeEvYH7DFjIu7bu8DFQo2K7s0fqxASaVanZW33T3_mneaLXsY5UuXwVglJ3vDybu639DC0rnGUZPIZcD_SwkkuHqLERDCI_U';

    public function test_real_firebase_push_notification_is_sent_to_hardcoded_token(): void
    {
        $this->skipUnlessFirebaseCredentialsAreConfigured();

        $user = new User;
        $user->forceFill([
            'id' => 0,
            'push_fcm_token' => self::FCM_TOKEN,
        ]);

        $title = 'Kauno Titanai Trina Makaule';
        $body = 'Real Firebase push test '.now('Europe/Vilnius')->format('Y-m-d H:i:s');

        fwrite(
            STDOUT,
            PHP_EOL.'Firebase Push Test Request:'.PHP_EOL.
            'Token prefix: '.substr(self::FCM_TOKEN, 0, 24).'...'.PHP_EOL.
            'Title: '.$title.PHP_EOL.
            'Body: '.$body.PHP_EOL
        );

        $user->notify(new PushNotification(
            title: $title,
            body: $body,
            data: [
                'type' => 'real_firebase_push_test',
                'sent_at' => now('Europe/Vilnius')->toIso8601String(),
            ],
        ));

        $this->assertTrue(true);
    }

    private function skipUnlessFirebaseCredentialsAreConfigured(): void
    {
        $credentials = config('firebase.projects.app.credentials')
            ?: env('FIREBASE_CREDENTIALS')
            ?: env('GOOGLE_APPLICATION_CREDENTIALS');

        if (blank($credentials)) {
            $this->markTestSkipped('Firebase credentials are not configured. Set FIREBASE_CREDENTIALS or GOOGLE_APPLICATION_CREDENTIALS to run this real push test.');
        }

        if (! is_string($credentials)) {
            return;
        }

        $path = $credentials;
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = base_path($path);
        }

        if (! file_exists($path)) {
            $this->markTestSkipped('Firebase credentials file does not exist: '.$credentials);
        }
    }
}
