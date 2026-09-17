<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PushNotification;
use Tests\TestCase;

class FirebasePushNotificationRealTest extends TestCase
{
    public function test_real_firebase_push_notification_is_sent(): void
    {
        $this->skipUnlessRealFirebaseTestsAreEnabled();

        $credentials = $this->firebaseCredentials();
        $this->skipUnlessFirebaseCredentialsAreAvailable($credentials);

        $token = env('FIREBASE_TEST_FCM_TOKEN');

        if (blank($token)) {
            $this->markTestSkipped(
                'FIREBASE_TEST_FCM_TOKEN is not configured.'
            );
        }

        $user = new User;

        $user->forceFill([
            'id' => 0,
            'push_fcm_token' => $token,
        ]);

        $title = 'Matau Kauną Firebase test';
        $body = 'Real Firebase push test '
            .now('Europe/Vilnius')->format('Y-m-d H:i:s');

        fwrite(
            STDOUT,
            PHP_EOL
            .'Firebase Push Test Request:'.PHP_EOL
            .'Token prefix: '.substr($token, 0, 24).'...'.PHP_EOL
            .'Title: '.$title.PHP_EOL
            .'Body: '.$body.PHP_EOL
        );

        $user->notify(
            new PushNotification(
                title: $title,
                body: $body,
                data: [
                    'type' => 'real_firebase_push_test',
                    'sent_at' => now('Europe/Vilnius')->toIso8601String(),
                ],
            )
        );

        $this->assertTrue(true);
    }

    private function skipUnlessRealFirebaseTestsAreEnabled(): void
    {
        if (env('RUN_REAL_FIREBASE_TESTS') !== 'true') {
            $this->markTestSkipped(
                'Real Firebase push test requires RUN_REAL_FIREBASE_TESTS=true.'
            );
        }
    }

    private function firebaseCredentials(): mixed
    {
        return config('firebase.projects.app.credentials')
            ?: env('FIREBASE_CREDENTIALS')
                ?: env('GOOGLE_APPLICATION_CREDENTIALS');
    }

    private function skipUnlessFirebaseCredentialsAreAvailable(
        mixed $credentials
    ): void {
        if (blank($credentials)) {
            $this->markTestSkipped(
                'Firebase credentials are not configured. '
                .'Set FIREBASE_CREDENTIALS or GOOGLE_APPLICATION_CREDENTIALS '
                .'to run this real push test.'
            );
        }

        if (! is_string($credentials)) {
            return;
        }

        $path = $credentials;

        if (
            ! str_starts_with($path, '/')
            && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)
        ) {
            $path = base_path($path);
        }

        if (! file_exists($path)) {
            $this->markTestSkipped(
                'Firebase credentials file does not exist: '.$credentials
            );
        }
    }
}
