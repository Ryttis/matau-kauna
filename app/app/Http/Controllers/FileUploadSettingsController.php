<?php

namespace App\Http\Controllers;

use App\Models\FileUploadSettings;
use Illuminate\Http\JsonResponse;

class FileUploadSettingsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'web' => FileUploadSettings::getChannelForClient(FileUploadSettings::CHANNEL_WEB),
            'mobile' => FileUploadSettings::getChannelForClient(FileUploadSettings::CHANNEL_MOBILE),
        ]);
    }
}
