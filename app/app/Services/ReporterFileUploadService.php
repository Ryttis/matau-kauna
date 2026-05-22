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
    public function __construct(
        private VideoUploadDurationValidator $videoDurationValidator,
    ) {}

    public function validateForChannel(array $files, string $channel, bool $photosOnly = false): void
    {
        $config = $this->uploadConfig($channel, $photosOnly);
        $this->validateFilesForConfig($files, $config, 0, 0);
    }

    public function validateAndStore(Problem $problem, array $files, string $channel, bool $appendToExisting = false, bool $photosOnly = false): array
    {
        $config = $this->uploadConfig($channel, $photosOnly);

        $photoBase = 0;
        $videoBase = 0;
        if ($appendToExisting) {
            $photoBase = (int) $problem->attachments()->where('kind', 'photo')->count();
            $videoBase = (int) $problem->attachments()->where('kind', 'video')->count();
        }

        $items = $this->validateFilesForConfig($files, $config, $photoBase, $videoBase);

        $created = [];

        foreach ($items as $item) {
            $created[] = $this->storeOne($problem, $item['file'], $item['mime'], $item['kind']);
        }

        return $created;
    }

    private function uploadConfig(string $channel, bool $photosOnly): array
    {
        $config = FileUploadSettings::getChannel($channel);
        if ($photosOnly) {
            $config['allow_video'] = false;
            $config['max_video_files_per_problem'] = 0;
        }

        return $config;
    }

    private function validateFilesForConfig(array $files, array $config, int $photoBase, int $videoBase): array
    {
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile && $f->isValid()));

        if ($files === []) {
            return [];
        }

        if (empty($config['allow_photo']) && empty($config['allow_video'])) {
            throw ValidationException::withMessages([
                'files' => 'Įkėlimas išjungtas.',
            ]);
        }

        $items = [];
        foreach ($files as $file) {
            $mime = strtolower((string) $file->getMimeType());
            $kind = FileUploadSettings::kindForMime($config, $mime);

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

            if ($kind === 'video') {
                $maxVideoKb = (int) ($config['max_video_size_kb'] ?? 51200);
                $sizeKb = (int) ceil($file->getSize() / 1024);
                if ($sizeKb > $maxVideoKb) {
                    throw ValidationException::withMessages([
                        'files' => 'Vaizdo failas per didelis.',
                    ]);
                }
            }

            if (! $this->extensionMatchesKind($file, $kind, $config)) {
                throw ValidationException::withMessages([
                    'files' => 'Netinkamas failo formatas.',
                ]);
            }

            if ($kind === 'video') {
                $maxVideoSec = (int) ($config['max_video_duration_seconds'] ?? VideoUploadDurationValidator::ABSOLUTE_MAX_SECONDS);
                $this->videoDurationValidator->assertWithinLimit($file, $maxVideoSec);
            }
        }

        return $items;
    }

    private function extensionMatchesKind(UploadedFile $file, string $kind, array $config): bool
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return FileUploadSettings::extensionAllowedForKind($config, $kind, $ext);
    }

    private function storeOne(Problem $problem, UploadedFile $file, string $mime, string $kind): ProblemAttachment
    {
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'bin';
        $safeExt = preg_match('/^[a-z0-9]{1,8}$/i', $ext) ? $ext : 'bin';
        $name = Str::uuid()->toString().'.'.$safeExt;

        $sub = $kind === 'photo' ? 'images' : 'videos';
        $relativeDir = 'files/problems/'.$problem->id.'/'.$sub;
        $absoluteDir = public_path($relativeDir);

        try {
            File::ensureDirectoryExists($absoluteDir);
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'files' => 'Failų įkėlimas laikinai nepasiekiamas. Bandykite vėliau arba kreipkitės į administratorių.',
            ]);
        }

        $originalName = $file->getClientOriginalName();
        $sizeBytes = (int) $file->getSize();

        $absolutePath = $absoluteDir.'/'.$name;

        try {
            $file->move($absoluteDir, $name);
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'files' => 'Nepavyko išsaugoti failo. Bandykite vėliau arba kreipkitės į administratorių.',
            ]);
        }

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
