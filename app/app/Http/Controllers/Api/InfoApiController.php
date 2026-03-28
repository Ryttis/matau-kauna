<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicStatisticsController;
use App\Models\FileUploadSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfoApiController extends Controller
{
    public function __construct(
        private PublicStatisticsController $publicStatisticsController
    ) {}

    public function statistics(Request $request): JsonResponse
    {
        return $this->publicStatisticsController->data($request);
    }

    public function fileUploadSettings(): JsonResponse
    {
        return response()->json([
            'web' => FileUploadSettings::getChannelForClient(FileUploadSettings::CHANNEL_WEB),
            'mobile' => FileUploadSettings::getChannelForClient(FileUploadSettings::CHANNEL_MOBILE),
            ...FileUploadSettings::phpIniLimits(),
        ]);
    }
}
