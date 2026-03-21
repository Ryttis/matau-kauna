<?php

namespace App\Services;

use App\Models\FileUploadSettings;
use App\Models\Problem;
use App\Models\ProblemAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReporterFileUploadService
{
    private const PHOTO_MIMES = ['image/jpeg', 'image/png'];

    private const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime'];

    public function validateAndStore(Problem $problem, array $files, string $channel, bool $appendToExisting = false, bool $photosOnly = false): array
    {
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile && $f->isValid()));

        if ($files === []) {
            return [];
        }

        $config = FileUploadSettings::getChannel($channel);
        if ($photosOnly) {
            $config['allow_video'] = false;
            $config['max_video_files_per_problem'] = 0;
        }

        if (empty($config['allow_photo']) && empty($config['allow_video'])) {
            throw ValidationException::withMessages([
                'files' => 'Įkėlimas išjungtas.',
            ]);
        }

        $photoBase = 0;
        $videoBase = 0;
        $totalKbBase = 0;

        if ($appendToExisting) {
            $photoBase = (int) $problem->attachments()->where('kind', 'photo')->count();
            $videoBase = (int) $problem->attachments()->where('kind', 'video')->count();
            $bytesSum = (int) $problem->attachments()->sum('size_bytes');
            $totalKbBase = (int) floor($bytesSum / 1024);
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

        $totalKb = $totalKbBase;
        $created = [];
        $maxTotalKb = (int) $config['max_total_upload_kb'];

        foreach ($items as $item) {
            $file = $item['file'];
            $kind = $item['kind'];
            $mime = $item['mime'];

            $maxKb = $kind === 'photo'
                ? (int) $config['max_photo_size_kb']
                : (int) $config['max_video_size_kb'];

            $sizeKb = (int) ceil($file->getSize() / 1024);

            if ($sizeKb > $maxKb) {
                throw ValidationException::withMessages([
                    'files' => 'Failas per didelis.',
                ]);
            }

            $totalKb += $sizeKb;

            if ($totalKb > $maxTotalKb) {
                throw ValidationException::withMessages([
                    'files' => 'Failai kartu per dideli.',
                ]);
            }

            if (! $this->extensionMatchesKind($file, $kind)) {
                throw ValidationException::withMessages([
                    'files' => 'Netinkamas failo formatas.',
                ]);
            }

            $created[] = $this->storeOne($problem, $file, $mime, $kind);
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

    private function storeOne(Problem $problem, UploadedFile $file, string $mime, string $kind): ProblemAttachment
    {
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'bin';
        $safeExt = preg_match('/^[a-z0-9]{1,8}$/i', $ext) ? $ext : 'bin';
        $name = Str::uuid()->toString().'.'.$safeExt;

        $sub = $kind === 'photo' ? 'images' : 'videos';
        $relativeDir = 'files/problems/'.$problem->id.'/'.$sub;
        $absoluteDir = public_path($relativeDir);

        File::ensureDirectoryExists($absoluteDir);

        $originalName = $file->getClientOriginalName();
        $sizeBytes = (int) $file->getSize();

        $absolutePath = $absoluteDir.'/'.$name;

        $file->move($absoluteDir, $name);

        $relativePath = $relativeDir.'/'.$name;

        try {
            return ProblemAttachment::create([
                'problem_id' => $problem->id,
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
