<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicStatisticsController;
use App\Models\ContentVersion;
use App\Models\FileUploadSettings;
use App\Models\User;
use App\Services\ProblemMapService;
use App\Support\Content\GuestPublishedContentCatalog;
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
        $definition = GuestPublishedContentCatalog::definitionForGuestPage($type);
        if ($definition === null) {
            abort(404);
        }

        $published = ContentVersion::getLatestPublished($definition->databaseType);

        return response()->json([
            'type' => $definition->databaseType,
            'title' => (string) ($published?->title ?? $definition->fallbackTitle),
            'body' => (string) ($published?->body ?? ''),
        ]);
    }

    public function fileUploadSettings(): JsonResponse
    {
        return response()->json([
            'web' => FileUploadSettings::getChannelForClient(FileUploadSettings::CHANNEL_WEB),
            'mobile' => FileUploadSettings::getChannelForClient(FileUploadSettings::CHANNEL_MOBILE),
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
