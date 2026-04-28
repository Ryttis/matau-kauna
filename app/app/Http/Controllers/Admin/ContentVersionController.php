<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentVersionPublishRequest;
use App\Http\Requests\Admin\ContentVersionStoreRequest;
use App\Http\Requests\Admin\ContentVersionUpdateRequest;
use App\Models\ContentVersion;
use App\Services\AuditLogService;
use App\Services\ContentVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentVersionController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private ContentVersionService $contentVersionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->validate([
            'type' => ['required', 'string', Rule::in(ContentVersion::administrableContentTypes())],
        ])['type'];
        $versions = $this->contentVersionService->getVersionsByType($type);

        return response()->json(['versions' => $versions]);
    }

    public function store(ContentVersionStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = $data['type'];

        try {
            $version = ContentVersion::nextVersionNumber($type);
            $content = ContentVersion::create([
                'type' => $type,
                'version' => $version,
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? '',
                'created_by' => $request->user()?->id,
            ]);
            $this->auditLogService->logUserAction($request->user(), 'content_version.create', 'ContentVersion', $content->id, AuditLogResult::Success, null, ['type' => $type]);

            return response()->json(['id' => $content->id, 'version' => $content->version], 201);
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'content_version.create', 'ContentVersion', null, AuditLogResult::Error, $e->getMessage(), ['type' => $type]);
            throw $e;
        }
    }

    public function update(ContentVersionUpdateRequest $request, ContentVersion $contentVersion): JsonResponse
    {
        try {
            $data = $request->validated();
            $contentVersion->update([
                'title' => $data['title'] ?? $contentVersion->title,
                'body' => $data['body'] ?? $contentVersion->body,
            ]);
            $this->auditLogService->logUserAction($request->user(), 'content_version.update', 'ContentVersion', $contentVersion->id, AuditLogResult::Success);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'content_version.update', 'ContentVersion', $contentVersion->id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function publish(ContentVersionPublishRequest $request, ContentVersion $contentVersion): JsonResponse
    {
        $requireReconsent = $request->validated('require_reconsent', false);
        try {
            $this->contentVersionService->publish($contentVersion, $requireReconsent);
            $this->auditLogService->logUserAction($request->user(), 'content_version.publish', 'ContentVersion', $contentVersion->id, AuditLogResult::Success, null, ['type' => $contentVersion->type]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'content_version.publish', 'ContentVersion', $contentVersion->id, AuditLogResult::Error, $e->getMessage(), ['type' => $contentVersion->type]);
            throw $e;
        }
    }

    public function unpublish(Request $request, ContentVersion $contentVersion): JsonResponse
    {
        if (! $contentVersion->is_published) {
            $msg = 'Versija nėra paskelbta.';
            $this->auditLogService->logUserAction($request->user(), 'content_version.unpublish', 'ContentVersion', $contentVersion->id, AuditLogResult::Error, $msg);

            return response()->json(['message' => $msg], 422);
        }

        try {
            $contentVersion->update(['is_published' => false]);
            $this->auditLogService->logUserAction($request->user(), 'content_version.unpublish', 'ContentVersion', $contentVersion->id, AuditLogResult::Success);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'content_version.unpublish', 'ContentVersion', $contentVersion->id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function destroy(Request $request, ContentVersion $contentVersion): JsonResponse
    {
        if ($contentVersion->is_published) {
            $msg = 'Publikuotos versijos pašalinti negalima.';
            $this->auditLogService->logUserAction($request->user(), 'content_version.destroy', 'ContentVersion', $contentVersion->id, AuditLogResult::Error, $msg);

            return response()->json(['message' => $msg], 422);
        }

        $id = $contentVersion->id;
        try {
            $contentVersion->delete();
            $this->auditLogService->logUserAction($request->user(), 'content_version.destroy', 'ContentVersion', $id, AuditLogResult::Success);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'content_version.destroy', 'ContentVersion', $id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }
}
