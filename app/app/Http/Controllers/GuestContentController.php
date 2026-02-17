<?php

namespace App\Http\Controllers;

use App\Models\ContentVersion;
use Inertia\Inertia;
use Inertia\Response;

class GuestContentController extends Controller
{
    public function faq(): Response
    {
        $content = ContentVersion::getLatestPublished(ContentVersion::TYPE_FAQ);

        return Inertia::render('Guest/Faq', [
            'title' => (string) ($content?->title ?? 'DUK'),
            'body' => (string) ($content?->body ?? ''),
        ]);
    }

    public function privacy(): Response
    {
        $content = ContentVersion::getLatestPublished(ContentVersion::TYPE_PRIVACY);

        return Inertia::render('Guest/PrivacyPolicy', [
            'title' => (string) ($content?->title ?? 'Privatumo politika'),
            'body' => (string) ($content?->body ?? ''),
        ]);
    }
}
