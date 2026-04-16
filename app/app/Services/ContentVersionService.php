<?php

namespace App\Services;

use App\Models\ContentVersion;
use App\Models\User;

class ContentVersionService
{
    public function getVersionsByType(string $type): array
    {
        return ContentVersion::query()
            ->ofType($type)
            ->with('creator:id,name')
            ->orderByDesc('version')
            ->get()
            ->map(fn (ContentVersion $v) => [
                'id' => $v->id,
                'version' => $v->version,
                'title' => $v->title,
                'body' => $v->body,
                'is_published' => $v->is_published,
                'published_at' => $v->published_at?->toIso8601String(),
                'require_reconsent' => $v->require_reconsent,
                'created_at' => $v->created_at->toIso8601String(),
                'creator_name' => $v->creator?->name,
            ])
            ->all();
    }

    public function publish(ContentVersion $contentVersion, bool $requireReconsent = false): void
    {
        ContentVersion::query()
            ->ofType($contentVersion->type)
            ->where('id', '!=', $contentVersion->id)
            ->update(['is_published' => false]);

        $contentVersion->update([
            'is_published' => true,
            'published_at' => now(),
            'require_reconsent' => $contentVersion->type === ContentVersion::TYPE_TERMS ? $requireReconsent : false,
        ]);

        if ($contentVersion->type === ContentVersion::TYPE_TERMS && $requireReconsent) {
            User::query()
                ->whereDoesntHave('roles.permissions', fn ($p) => $p->where('permission', 'issues.view'))
                ->update(['terms_accepted_at' => null]);
        }
    }
}
