<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePublicStatisticsConfigRequest;
use App\Models\PublicStatisticsConfig;
use Illuminate\Http\JsonResponse;

class PublicStatisticsConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['config' => PublicStatisticsConfig::get()]);
    }

    public function update(UpdatePublicStatisticsConfigRequest $request): JsonResponse
    {
        PublicStatisticsConfig::set($request->validated('config'));

        return response()->json(['ok' => true]);
    }
}
