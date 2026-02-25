<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentVersionPublishRequest;
use App\Http\Requests\Admin\ContentVersionStoreRequest;
use App\Http\Requests\Admin\ContentVersionUpdateRequest;
use App\Models\ContentVersion;
use App\Services\AuditLogService;
use App\Services\ContentVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentVersionController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private ContentVersionService $contentVersionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->validate(['type' => ['required', 'string', 'in:terms,privacy,faq,cookies']])['type'];
        $versions = $this->contentVersionService->getVersionsByType($type);

        return response()->json(['versions' => $versions]);
    }

    public function store(ContentVersionStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = $data['type'];
        $version = ContentVersion::nextVersionNumber($type);

        $content = ContentVersion::create([
            'type' => $type,
            'version' => $version,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? '',
            'created_by' => $request->user()?->id,
        ]);
        $this->auditLogService->logUserAction($request->user(), 'content_version.create', 'ContentVersion', $content->id, 'success', null, ['type' => $type]);

        return response()->json(['id' => $content->id, 'version' => $content->version], 201);
    }

    public function update(ContentVersionUpdateRequest $request, ContentVersion $contentVersion): JsonResponse
    {
        if ($contentVersion->is_published) {
            return response()->json(['message' => 'Publikuotos versijos redaguoti negalima.'], 422);
        }

        $data = $request->validated();
        $contentVersion->update([
            'title' => $data['title'] ?? $contentVersion->title,
            'body' => $data['body'] ?? $contentVersion->body,
        ]);
        $this->auditLogService->logUserAction($request->user(), 'content_version.update', 'ContentVersion', $contentVersion->id, 'success');

        return response()->json(['ok' => true]);
    }

    public function publish(ContentVersionPublishRequest $request, ContentVersion $contentVersion): JsonResponse
    {
        $requireReconsent = $request->validated('require_reconsent', false);
        $this->contentVersionService->publish($contentVersion, $requireReconsent);
        $this->auditLogService->logUserAction($request->user(), 'content_version.publish', 'ContentVersion', $contentVersion->id, 'success', null, ['type' => $contentVersion->type]);

        return response()->json(['ok' => true]);
    }

    public function unpublish(Request $request, ContentVersion $contentVersion): JsonResponse
    {
        if (! $contentVersion->is_published) {
            return response()->json(['message' => 'Versija nėra paskelbta.'], 422);
        }

        $contentVersion->update(['is_published' => false]);
        $this->auditLogService->logUserAction($request->user(), 'content_version.unpublish', 'ContentVersion', $contentVersion->id, 'success');

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, ContentVersion $contentVersion): JsonResponse
    {
        if ($contentVersion->is_published) {
            return response()->json(['message' => 'Publikuotos versijos pašalinti negalima.'], 422);
        }

        $id = $contentVersion->id;
        $contentVersion->delete();
        $this->auditLogService->logUserAction($request->user(), 'content_version.destroy', 'ContentVersion', $id, 'success');

        return response()->json(['ok' => true]);
    }
}
