<?php

namespace App\Console\Commands;

use App\Models\ProblemAttachment;
use App\Models\ProblemReviewAttachment;
use App\Services\HeicImageConverter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class ConvertHeicAttachmentsCommand extends Command
{
    protected $signature = 'problems:convert-heic-attachments {--dry-run : Tik parodyti, kas būtų pakeista}';

    protected $description = 'Konvertuoja jau įkeltus HEIC/HEIF priedus į JPEG, kad naršyklės juos atvaizduotų';

    public function handle(HeicImageConverter $converter): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! HeicImageConverter::isSupported()) {
            $this->error('Imagick su HEIC palaikymu nepasiekiamas - konvertuoti negalima.');

            return self::FAILURE;
        }

        $converted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ([ProblemAttachment::class, ProblemReviewAttachment::class] as $modelClass) {
            $this->line($modelClass.':');

            $this->heicQuery($modelClass)->chunkById(100, function ($attachments) use (
                $converter, $dryRun, &$converted, &$skipped, &$failed
            ) {
                foreach ($attachments as $attachment) {
                    $result = $this->convertAttachment($attachment, $converter, $dryRun);
                    match ($result) {
                        'converted' => $converted++,
                        'skipped' => $skipped++,
                        default => $failed++,
                    };
                }
            });
        }

        $prefix = $dryRun ? 'Būtų konvertuota' : 'Konvertuota';
        $this->info("{$prefix} priedų: {$converted}.");
        if ($skipped > 0) {
            $this->warn("Praleista (failo nėra diske): {$skipped}.");
        }
        if ($failed > 0) {
            $this->error("Nepavyko konvertuoti: {$failed}.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function heicQuery(string $modelClass): Builder
    {
        return $modelClass::query()->where(function (Builder $query) {
            $query->whereIn('mime_type', HeicImageConverter::CONVERTIBLE_MIMES);
            foreach (HeicImageConverter::CONVERTIBLE_EXTENSIONS as $extension) {
                $query->orWhere('path', 'like', '%.'.$extension);
            }
        });
    }

    private function convertAttachment(Model $attachment, HeicImageConverter $converter, bool $dryRun): string
    {
        $relativePath = ltrim((string) $attachment->path, '/');
        $absolutePath = public_path($relativePath);

        if (! File::exists($absolutePath)) {
            $this->warn("  #{$attachment->id}: failo nėra - {$relativePath}");

            return 'skipped';
        }

        $jpegRelativePath = $this->jpegPath($relativePath, $converter);

        if ($dryRun) {
            $this->line("  #{$attachment->id}: {$relativePath} -> {$jpegRelativePath}");

            return 'converted';
        }

        try {
            $sizeBytes = $converter->convertToJpeg($absolutePath, public_path($jpegRelativePath));
        } catch (\Throwable $e) {
            report($e);
            $this->error("  #{$attachment->id}: {$e->getMessage()}");

            return 'failed';
        }

        $attachment->forceFill([
            'path' => $jpegRelativePath,
            'mime_type' => HeicImageConverter::JPEG_MIME,
            'size_bytes' => $sizeBytes,
            'original_name' => $attachment->original_name !== null
                ? $converter->jpegFileName((string) $attachment->original_name)
                : null,
        ])->save();

        $this->line("  #{$attachment->id}: {$relativePath} -> {$jpegRelativePath}");

        return 'converted';
    }

    private function jpegPath(string $relativePath, HeicImageConverter $converter): string
    {
        $dir = trim((string) pathinfo($relativePath, PATHINFO_DIRNAME), '.');
        $fileName = $converter->jpegFileName(basename($relativePath));

        return $dir !== '' ? $dir.'/'.$fileName : $fileName;
    }
}
