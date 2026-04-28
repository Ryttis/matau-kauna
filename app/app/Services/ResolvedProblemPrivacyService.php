<?php

namespace App\Services;

use App\Models\Problem;
use App\Models\ResolvedProblemPrivacySetting;
use Illuminate\Support\Carbon;

class ResolvedProblemPrivacyService
{
    public function hideExpiredResolvedProblems(): int
    {
        $setting = ResolvedProblemPrivacySetting::get();
        if (! $setting || ! $setting->is_active) {
            return 0;
        }

        $days = max(1, (int) $setting->days_after_resolution);
        $threshold = Carbon::now()->subDays($days);

        return Problem::query()
            ->resolvedPublicRoot()
            ->where('status_updated_at', '<=', $threshold)
            ->update(['is_published_publicly' => false]);
    }
}
