<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailPreviewJob;
use App\Services\EmailPreviewSender;
use Illuminate\Console\Command;
use Throwable;

class MailSendPreviewsCommand extends Command
{
    protected $signature = 'mail:send-previews
                            {email? : Gautojo el. paštas}
                            {--queued : Siųsti per eilę (queue worker turi veikti)}
                            {--delay=2000 : Bazinė pauzė tarp laiškų (ms); pridedamas atsitiktinis jitter, SMTP riboms}';

    protected $description = 'Išsiunčia visus sistemos el. laiškų pavyzdžius (šablonai iš DB, lietuviškas turinys, stilius kaip produkcijoje)';

    public function handle(EmailPreviewSender $sender): int
    {
        $to = $this->argument('email') ?: config('mail.preview_recipient');
        if (! is_string($to) || $to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Nurodykite galiojantį el. paštą arba nustatykite MAIL_PREVIEW_RECIPIENT .env faile.');

            return self::FAILURE;
        }

        $kinds = EmailPreviewSender::kinds();
        $this->info('Siunčiama '.$to.' ('.count($kinds).' laiškų)…');

        if ($this->option('queued')) {
            $staggerMs = max(0, (int) $this->option('delay'));
            foreach ($kinds as $index => $kind) {
                $pending = SendEmailPreviewJob::dispatch($kind, $to);
                if ($staggerMs > 0) {
                    $pending->delay(now()->addMilliseconds($index * $staggerMs));
                }
            }
            $this->info('Užduotys įdėtos į eilę.'.($staggerMs > 0 ? ' (išdėstytos pagal --delay.)' : ''));

            return self::SUCCESS;
        }

        $basePauseUs = max(0, (int) $this->option('delay')) * 1000;
        $failed = [];
        foreach ($kinds as $index => $kind) {
            if ($index > 0 && $basePauseUs > 0) {
                $jitterUs = random_int(200_000, 700_000);
                usleep($basePauseUs + $jitterUs);
            }
            try {
                $this->sendKindWithRateLimitRetry($sender, $kind, $to);
                $this->line(' ✓ '.$kind);
            } catch (Throwable $e) {
                $failed[$kind] = $e->getMessage();
                $this->error(' ✗ '.$kind.': '.$e->getMessage());
            }
        }

        if ($failed !== []) {
            $this->warn('Baigta su klaidomis: '.count($failed).' iš '.count($kinds).'.');

            return self::FAILURE;
        }

        $this->info('Visi laiškai išsiųsti.');

        return self::SUCCESS;
    }

    private function sendKindWithRateLimitRetry(EmailPreviewSender $sender, string $kind, string $to): void
    {
        try {
            $sender->sendKind($kind, $to);
        } catch (Throwable $e) {
            if (! $this->isSmtpRateLimitMessage($e->getMessage())) {
                throw $e;
            }
            sleep(5);
            $sender->sendKind($kind, $to);
        }
    }

    private function isSmtpRateLimitMessage(string $message): bool
    {
        return str_contains($message, 'Too many emails')
            || str_contains($message, '550 5.7.0');
    }
}
