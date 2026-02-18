<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentVersion;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentVersionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->validate(['type' => ['required', 'string', 'in:terms,privacy,faq']])['type'];

        $versions = ContentVersion::query()
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
            ]);

        return response()->json(['versions' => $versions]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:terms,privacy,faq'],
            'title' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
        ]);

        $type = $data['type'];
        $version = ContentVersion::nextVersionNumber($type);

        $content = ContentVersion::create([
            'type' => $type,
            'version' => $version,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? '',
            'created_by' => $request->user()?->id,
        ]);
        app(AuditLogService::class)->logUserAction($request->user(), 'content_version.create', 'ContentVersion', $content->id, 'success', null, ['type' => $type]);

        return response()->json(['id' => $content->id, 'version' => $content->version], 201);
    }

    public function update(Request $request, ContentVersion $contentVersion): JsonResponse
    {
        if ($contentVersion->is_published) {
            return response()->json(['message' => 'Publikuotos versijos redaguoti negalima.'], 422);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
        ]);

        $contentVersion->update([
            'title' => $data['title'] ?? $contentVersion->title,
            'body' => $data['body'] ?? $contentVersion->body,
        ]);
        app(AuditLogService::class)->logUserAction($request->user(), 'content_version.update', 'ContentVersion', $contentVersion->id, 'success');

        return response()->json(['ok' => true]);
    }

    public function publish(Request $request, ContentVersion $contentVersion): JsonResponse
    {
        $data = $request->validate([
            'require_reconsent' => ['boolean'],
        ]);

        $requireReconsent = $data['require_reconsent'] ?? false;

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
                ->whereHas('roles', fn ($q) => $q->where('role', UserRole::ROLE_REPORTER))
                ->update(['terms_accepted_at' => null]);
        }
        app(AuditLogService::class)->logUserAction($request->user(), 'content_version.publish', 'ContentVersion', $contentVersion->id, 'success', null, ['type' => $contentVersion->type]);

        return response()->json(['ok' => true]);
    }

    public function unpublish(Request $request, ContentVersion $contentVersion): JsonResponse
    {
        if (! $contentVersion->is_published) {
            return response()->json(['message' => 'Versija nėra paskelbta.'], 422);
        }

        $contentVersion->update(['is_published' => false]);
        app(AuditLogService::class)->logUserAction($request->user(), 'content_version.unpublish', 'ContentVersion', $contentVersion->id, 'success');

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, ContentVersion $contentVersion): JsonResponse
    {
        if ($contentVersion->is_published) {
            return response()->json(['message' => 'Publikuotos versijos pašalinti negalima.'], 422);
        }

        $id = $contentVersion->id;
        $contentVersion->delete();
        app(AuditLogService::class)->logUserAction($request->user(), 'content_version.destroy', 'ContentVersion', $id, 'success');

        return response()->json(['ok' => true]);
    }
}
