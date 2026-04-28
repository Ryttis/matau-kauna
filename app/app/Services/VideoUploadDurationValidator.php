<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class VideoUploadDurationValidator
{
    public const ABSOLUTE_MAX_SECONDS = 60;

    public function assertWithinLimit(UploadedFile $file, int $maxSeconds): void
    {
        $maxSeconds = max(1, min($maxSeconds, self::ABSOLUTE_MAX_SECONDS));
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'files' => 'Nepavyko patikrinti vaizdo įrašo trukmės.',
            ]);
        }

        $getID3 = new \getID3;
        $info = $getID3->analyze($path);

        if (! empty($info['error'])) {
            throw ValidationException::withMessages([
                'files' => 'Vaizdo įrašas neatpažįstamas arba sugadintas.',
            ]);
        }

        $seconds = isset($info['playtime_seconds']) ? (float) $info['playtime_seconds'] : null;
        if ($seconds === null || $seconds <= 0 || ! is_finite($seconds)) {
            throw ValidationException::withMessages([
                'files' => 'Nepavyko nustatyti vaizdo įrašo trukmės.',
            ]);
        }

        if (round($seconds, 1) > $maxSeconds) {
            throw ValidationException::withMessages([
                'files' => $this->tooLongMessage($maxSeconds),
            ]);
        }
    }

    private function tooLongMessage(int $maxSeconds): string
    {
        if ($maxSeconds >= 60) {
            return 'Vaizdo įrašas turi būti ne ilgesnis kaip 1 min.';
        }

        return sprintf('Vaizdo įrašas turi būti ne ilgesnis kaip %d s.', $maxSeconds);
    }
}
