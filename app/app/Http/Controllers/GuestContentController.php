<?php

namespace App\Http\Controllers;

use App\Models\ContentVersion;
use Inertia\Inertia;
use Inertia\Response;

class GuestContentController extends Controller
{
    public function faq(): Response
    {
        return $this->renderContentPage(ContentVersion::TYPE_FAQ, 'Guest/Faq', 'DUK');
    }

    public function privacy(): Response
    {
        return $this->renderContentPage(ContentVersion::TYPE_PRIVACY, 'Guest/PrivacyPolicy', 'Privatumo politika');
    }

    public function cookies(): Response
    {
        return $this->renderContentPage(ContentVersion::TYPE_COOKIES, 'Guest/CookiePolicy', 'Slapukų informacija');
    }

    private function renderContentPage(string $type, string $view, string $defaultTitle): Response
    {
        $content = ContentVersion::getLatestPublished($type);

        return Inertia::render($view, [
            'title' => (string) ($content?->title ?? $defaultTitle),
            'body' => (string) ($content?->body ?? ''),
        ]);
    }
}
