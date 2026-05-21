<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFileUploadSettingsRequest;
use App\Models\FileUploadSettings;
use Illuminate\Http\JsonResponse;

class FileUploadSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'config' => FileUploadSettings::getFull(),
        ]);
    }

    public function update(UpdateFileUploadSettingsRequest $request): JsonResponse
    {
        $config = $request->validated('config');

        FileUploadSettings::set($config);

        return response()->json(['ok' => true]);
    }
}
