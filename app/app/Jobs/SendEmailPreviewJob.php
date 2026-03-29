<?php

namespace App\Jobs;

use App\Services\EmailPreviewSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $kind,
        public string $recipient,
    ) {}

    public function handle(EmailPreviewSender $sender): void
    {
        $sender->sendKind($this->kind, $this->recipient);
    }
}
