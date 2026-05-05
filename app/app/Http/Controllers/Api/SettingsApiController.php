<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsApiController extends Controller
{
    public function updateEmailNotifications(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reporter_email_own_problems' => ['required', 'boolean'],
            'reporter_email_watched_problems' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'reporter_email_own_problems' => $request->boolean('reporter_email_own_problems'),
            'reporter_email_watched_problems' => $request->boolean('reporter_email_watched_problems'),
        ])->save();

        return response()->json([
            'ok' => true,
            'email_notifications' => [
                'reporter_email_own_problems' => (bool) $user->reporter_email_own_problems,
                'reporter_email_watched_problems' => (bool) $user->reporter_email_watched_problems,
            ],
        ]);
    }
}
