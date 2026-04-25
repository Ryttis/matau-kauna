<?php

namespace App\Http\Controllers;

use App\Models\ContentVersion;
use App\Support\Content\GuestPublishedContentCatalog;
use Inertia\Inertia;
use Inertia\Response;

class GuestContentController extends Controller
{
    public function terms(): Response
    {
        return $this->renderGuestPage(ContentVersion::TYPE_TERMS);
    }

    public function faq(): Response
    {
        return $this->renderGuestPage(ContentVersion::TYPE_FAQ);
    }

    public function privacy(): Response
    {
        return $this->renderGuestPage(ContentVersion::TYPE_PRIVACY);
    }

    public function cookies(): Response
    {
        return $this->renderGuestPage(ContentVersion::TYPE_COOKIES);
    }

    public function contacts(): Response
    {
        return $this->renderGuestPage(ContentVersion::TYPE_CONTACTS);
    }

    public function help(): Response
    {
        return $this->renderGuestPage(ContentVersion::TYPE_HELP);
    }

    private function renderGuestPage(string $databaseType): Response
    {
        $definition = GuestPublishedContentCatalog::definitionForGuestPage($databaseType);
        if ($definition === null) {
            abort(404);
        }

        $published = ContentVersion::getLatestPublished($databaseType);

        return Inertia::render($definition->inertiaView, [
            'title' => (string) ($published?->title ?? $definition->fallbackTitle),
            'body' => (string) ($published?->body ?? ''),
        ]);
    }
}
