<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailNotificationTemplate;
use App\Support\EmailBody;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmailNotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = EmailNotificationTemplate::orderBy('key')->get();

        return response()->json(['templates' => $templates]);
    }

    public function previewBody(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:65000'],
        ]);

        $html = trim(Str::markdown($validated['body'], EmailBody::markdownConverterOptions()));

        return response()->json(['html' => $html]);
    }

    public function update(Request $request, EmailNotificationTemplate $emailNotification): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $emailNotification->fill($validated);
        $emailNotification->save();

        return response()->json(['template' => $emailNotification]);
    }
}
