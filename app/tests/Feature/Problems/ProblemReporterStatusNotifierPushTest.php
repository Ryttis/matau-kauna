<?php

namespace Tests\Feature\Problems;

use App\Enums\ProblemStatus;
use App\Models\Problem;
use App\Models\ProblemWatch;
use App\Models\User;
use App\Notifications\PushNotification;
use App\Services\ProblemReporterStatusNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class ProblemReporterStatusNotifierPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_change_sends_push_to_opted_in_reporter_with_token(): void
    {
        Notification::fake();

        $reporter = $this->pushUser([
            'email_notifications_enabled' => true,
            'reporter_email_own_problems' => true,
        ]);
        $problem = $this->problem(['reported_by_user_id' => $reporter->id]);

        $this->notifier()->notifyReportersAndWatchersWhenStatusChanges(
            $problem,
            ProblemStatus::NEW->value,
            ProblemStatus::IN_REVIEW->value,
        );

        Notification::assertSentTo($reporter, PushNotification::class);
    }

    public function test_status_change_does_not_send_reporter_push_when_notifications_are_disabled(): void
    {
        Notification::fake();

        $reporter = $this->pushUser([
            'email_notifications_enabled' => false,
            'reporter_email_own_problems' => true,
        ]);
        $problem = $this->problem(['reported_by_user_id' => $reporter->id]);

        $this->notifier()->notifyReportersAndWatchersWhenStatusChanges(
            $problem,
            ProblemStatus::NEW->value,
            ProblemStatus::IN_REVIEW->value,
        );

        Notification::assertNotSentTo($reporter, PushNotification::class);
    }

    public function test_status_change_does_not_send_reporter_push_when_own_problem_notifications_are_disabled(): void
    {
        Notification::fake();

        $reporter = $this->pushUser([
            'email_notifications_enabled' => true,
            'reporter_email_own_problems' => false,
        ]);
        $problem = $this->problem(['reported_by_user_id' => $reporter->id]);

        $this->notifier()->notifyReportersAndWatchersWhenStatusChanges(
            $problem,
            ProblemStatus::NEW->value,
            ProblemStatus::IN_REVIEW->value,
        );

        Notification::assertNotSentTo($reporter, PushNotification::class);
    }

    public function test_status_change_watcher_push_respects_watched_problem_notifications(): void
    {
        Notification::fake();

        $enabledWatcher = $this->pushUser([
            'email_notifications_enabled' => true,
            'reporter_email_watched_problems' => true,
        ]);
        $disabledWatcher = $this->pushUser([
            'email_notifications_enabled' => true,
            'reporter_email_watched_problems' => false,
        ]);
        $problem = $this->problem();

        ProblemWatch::create([
            'user_id' => $enabledWatcher->id,
            'problem_id' => $problem->id,
        ]);
        ProblemWatch::create([
            'user_id' => $disabledWatcher->id,
            'problem_id' => $problem->id,
        ]);

        $this->notifier()->notifyReportersAndWatchersWhenStatusChanges(
            $problem,
            ProblemStatus::NEW->value,
            ProblemStatus::IN_REVIEW->value,
        );

        Notification::assertSentTo($enabledWatcher, PushNotification::class);
        Notification::assertNotSentTo($disabledWatcher, PushNotification::class);
    }

    public function test_production_code_does_not_contain_hardcoded_fcm_tokens(): void
    {
        $matches = [];

        foreach ([app_path(), config_path(), base_path('routes')] as $path) {
            foreach ($this->filesUnder($path) as $file) {
                $contents = file_get_contents($file->getPathname());
                if (preg_match('/[A-Za-z0-9_-]{10,}:APA91[A-Za-z0-9_-]+/', $contents ?? '')) {
                    $matches[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $matches);
    }

    private function notifier(): ProblemReporterStatusNotifier
    {
        return app(ProblemReporterStatusNotifier::class);
    }

    private function pushUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'push_fcm_token' => 'test-fcm-token-'.uniqid(),
            'email_notifications_enabled' => true,
            'reporter_email_own_problems' => true,
            'reporter_email_watched_problems' => true,
        ], $attributes));
    }

    private function problem(array $attributes = []): Problem
    {
        return Problem::create(array_merge([
            'status' => ProblemStatus::NEW->value,
            'category' => 'Keliai',
            'address' => 'Laisves al., Kaunas',
            'description' => 'Bandomoji problema',
            'lat' => 54.8985,
            'lng' => 23.9036,
        ], $attributes));
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function filesUnder(string $path): iterable
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                yield $file;
            }
        }
    }
}
