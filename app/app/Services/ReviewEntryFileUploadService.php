<?php

namespace App\Services;

use App\Models\FileUploadSettings;
use App\Models\Problem;
use App\Models\ProblemReviewAttachment;
use App\Models\ProblemReviewHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewEntryFileUploadService
{
    private const PHOTO_MIMES = ['image/jpeg', 'image/png'];

    private const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime'];

    public function __construct(
        private VideoUploadDurationValidator $videoDurationValidator,
    ) {}

    public function validateAndStore(Problem $problem, ProblemReviewHistory $entry, array $files, string $channel): array
    {
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile && $f->isValid()));

        if ($files === []) {
            return [];
        }

        $photoBase = (int) $entry->reviewAttachments()->where('kind', 'photo')->count();
        $videoBase = (int) $entry->reviewAttachments()->where('kind', 'video')->count();
        $config = FileUploadSettings::getChannel($channel);

        if (empty($config['allow_photo']) && empty($config['allow_video'])) {
            throw ValidationException::withMessages([
                'files' => 'Įkėlimas išjungtas.',
            ]);
        }

        $items = [];
        foreach ($files as $file) {
            $mime = strtolower((string) $file->getMimeType());
            $kind = $this->mimeToKind($mime);

            if ($kind === null) {
                throw ValidationException::withMessages([
                    'files' => 'Netinkamas failo tipas.',
                ]);
            }

            if ($kind === 'photo' && empty($config['allow_photo'])) {
                throw ValidationException::withMessages([
                    'files' => 'Nuotraukos neleidžiamos.',
                ]);
            }

            if ($kind === 'video' && empty($config['allow_video'])) {
                throw ValidationException::withMessages([
                    'files' => 'Vaizdai neleidžiami.',
                ]);
            }

            $items[] = ['file' => $file, 'kind' => $kind, 'mime' => $mime];
        }

        $maxPhoto = (int) ($config['max_photo_files_per_problem'] ?? 5);
        $maxVideo = (int) ($config['max_video_files_per_problem'] ?? 1);
        $newPhotoCount = 0;
        $newVideoCount = 0;
        foreach ($items as $item) {
            if ($item['kind'] === 'photo') {
                $newPhotoCount++;
            } else {
                $newVideoCount++;
            }
        }

        if ($photoBase + $newPhotoCount > $maxPhoto) {
            throw ValidationException::withMessages([
                'files' => 'Per daug nuotraukų.',
            ]);
        }

        if ($videoBase + $newVideoCount > $maxVideo) {
            throw ValidationException::withMessages([
                'files' => 'Per daug vaizdų.',
            ]);
        }

        $created = [];

        foreach ($items as $item) {
            $file = $item['file'];
            $kind = $item['kind'];
            $mime = $item['mime'];

            if ($kind === 'photo') {
                $maxPhotoKb = (int) ($config['max_photo_size_kb'] ?? 5120);
                $sizeKb = (int) ceil($file->getSize() / 1024);
                if ($sizeKb > $maxPhotoKb) {
                    throw ValidationException::withMessages([
                        'files' => 'Failas per didelis.',
                    ]);
                }
            }

            if (! $this->extensionMatchesKind($file, $kind)) {
                throw ValidationException::withMessages([
                    'files' => 'Netinkamas failo formatas.',
                ]);
            }

            if ($kind === 'video') {
                $maxVideoSec = (int) ($config['max_video_duration_seconds'] ?? VideoUploadDurationValidator::ABSOLUTE_MAX_SECONDS);
                $this->videoDurationValidator->assertWithinLimit($file, $maxVideoSec);
            }

            $created[] = $this->storeOne($problem, $entry, $file, $mime, $kind);
        }

        return $created;
    }

    private function mimeToKind(string $mime): ?string
    {
        if (in_array($mime, self::PHOTO_MIMES, true)) {
            return 'photo';
        }
        if (in_array($mime, self::VIDEO_MIMES, true)) {
            return 'video';
        }

        return null;
    }

    private function extensionMatchesKind(UploadedFile $file, string $kind): bool
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if ($kind === 'photo') {
            return in_array($ext, ['jpg', 'jpeg', 'png'], true);
        }

        return in_array($ext, ['mp4', 'webm', 'mov'], true);
    }

    private function storeOne(
        Problem $problem,
        ProblemReviewHistory $entry,
        UploadedFile $file,
        string $mime,
        string $kind
    ): ProblemReviewAttachment {
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'bin';
        $safeExt = preg_match('/^[a-z0-9]{1,8}$/i', $ext) ? $ext : 'bin';
        $name = Str::uuid()->toString().'.'.$safeExt;

        $sub = $kind === 'photo' ? 'images' : 'videos';
        $relativeDir = 'files/problems/'.$problem->id.'/review/'.$entry->id.'/'.$sub;
        $absoluteDir = public_path($relativeDir);

        File::ensureDirectoryExists($absoluteDir);

        $originalName = $file->getClientOriginalName();
        $sizeBytes = (int) $file->getSize();

        $absolutePath = $absoluteDir.'/'.$name;

        $file->move($absoluteDir, $name);

        $relativePath = $relativeDir.'/'.$name;

        try {
            return ProblemReviewAttachment::create([
                'problem_review_history_id' => $entry->id,
                'path' => $relativePath,
                'original_name' => $originalName,
                'mime_type' => $mime,
                'size_bytes' => $sizeBytes,
                'kind' => $kind,
            ]);
        } catch (\Throwable $e) {
            if (File::exists($absolutePath)) {
                File::delete($absolutePath);
            }

            throw $e;
        }
    }
}
