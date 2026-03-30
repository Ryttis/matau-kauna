<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicStatisticsController;
use App\Models\ContentVersion;
use App\Models\FileUploadSettings;
use App\Models\User;
use App\Services\ProblemMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class InfoApiController extends Controller
{
    public function __construct(
        private PublicStatisticsController $publicStatisticsController,
        private ProblemMapService $mapService,
    ) {}

    public function statistics(Request $request): JsonResponse
    {
        return $this->publicStatisticsController->data($request);
    }

    public function mapProblems(Request $request): JsonResponse
    {
        $authenticatedUser = $this->userFromOptionalBearerToken($request);

        return response()->json([
            'center' => [
                'lat' => (float) config('kaunas.map_center.lat'),
                'lng' => (float) config('kaunas.map_center.lng'),
            ],
            'problems' => $this->mapService->getPublicProblemsForMap(null, $authenticatedUser),
        ]);
    }

    public function content(string $type): JsonResponse
    {
        $publishedTypeAndFallbackTitle = [
            'faq' => [ContentVersion::TYPE_FAQ, 'DUK'],
            'privacy' => [ContentVersion::TYPE_PRIVACY, 'Privatumo politika'],
            'cookies' => [ContentVersion::TYPE_COOKIES, 'Slapukų informacija'],
        ];

        if (! isset($publishedTypeAndFallbackTitle[$type])) {
            abort(404);
        }

        [$contentType, $fallbackTitle] = $publishedTypeAndFallbackTitle[$type];
        $version = ContentVersion::getLatestPublished($contentType);

        return response()->json([
            'type' => $type,
            'title' => (string) ($version?->title ?? $fallbackTitle),
            'body' => (string) ($version?->body ?? ''),
        ]);
    }

    public function fileUploadSettings(): JsonResponse
    {
        return response()->json([
            'web' => FileUploadSettings::getChannelForClient(FileUploadSettings::CHANNEL_WEB),
            'mobile' => FileUploadSettings::getChannelForClient(FileUploadSettings::CHANNEL_MOBILE),
            ...FileUploadSettings::phpIniLimits(),
        ]);
    }

    private function userFromOptionalBearerToken(Request $request): ?User
    {
        $token = $request->bearerToken();
        if (! is_string($token) || $token === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        $model = $accessToken?->tokenable;

        return $model instanceof User ? $model : null;
    }
}
