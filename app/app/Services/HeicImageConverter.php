<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * HEIC/HEIF nuotraukos konvertuojamos į JPEG, nes naršyklės (išskyrus Safari)
 * HEIC neatvaizduoja - <img src="....heic"> lieka tuščia vieta.
 */
class HeicImageConverter
{
    public const CONVERTIBLE_MIMES = [
        'image/heic',
        'image/heif',
        'image/heic-sequence',
        'image/heif-sequence',
    ];

    public const CONVERTIBLE_EXTENSIONS = ['heic', 'heif', 'hif'];

    public const JPEG_MIME = 'image/jpeg';

    public const JPEG_EXTENSION = 'jpg';

    private const JPEG_QUALITY = 85;

    public static function isSupported(): bool
    {
        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            return false;
        }

        try {
            return \Imagick::queryFormats('HEIC') !== [];
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function needsConversion(?string $mime, ?string $extension): bool
    {
        $mime = strtolower(trim((string) $mime));
        if (in_array($mime, self::CONVERTIBLE_MIMES, true)) {
            return true;
        }

        $extension = strtolower(ltrim(trim((string) $extension), '.'));

        return in_array($extension, self::CONVERTIBLE_EXTENSIONS, true);
    }

    /**
     * Failo pavadinimas su .jpg plėtiniu (naudojama ir path, ir original_name).
     */
    public function jpegFileName(string $fileName): string
    {
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $base = $base !== '' ? $base : 'nuotrauka';

        return $base.'.'.self::JPEG_EXTENSION;
    }

    /**
     * Konvertuoja HEIC/HEIF failą į JPEG ir grąžina naujo failo dydį baitais.
     * Šaltinio failas ištrinamas tik po sėkmingo įrašymo.
     */
    public function convertToJpeg(string $sourceAbsolutePath, string $targetAbsolutePath): int
    {
        if (! self::isSupported()) {
            throw new RuntimeException('Imagick su HEIC palaikymu nepasiekiamas.');
        }

        if (! File::exists($sourceAbsolutePath)) {
            throw new RuntimeException('HEIC failas nerastas: '.$sourceAbsolutePath);
        }

        $image = null;

        try {
            $image = new \Imagick($sourceAbsolutePath);

            // HEIC gali turėti kelis kadrus (burst, "live photo") - imame pirmąjį.
            if ($image->getNumberImages() > 1) {
                $image->setIteratorIndex(0);
            }

            // autoOrient prieš stripImage, kitaip nuotrauka lieka pasukta:
            // EXIF orientacijos vėliavėlė pašalinama kartu su profiliais.
            $image->autoOrient();
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(self::JPEG_QUALITY);
            $image->stripImage();

            if ($image->writeImage($targetAbsolutePath) !== true) {
                throw new RuntimeException('Nepavyko įrašyti JPEG failo.');
            }
        } catch (\Throwable $e) {
            if (File::exists($targetAbsolutePath) && $targetAbsolutePath !== $sourceAbsolutePath) {
                File::delete($targetAbsolutePath);
            }

            throw new RuntimeException('Nepavyko konvertuoti HEIC nuotraukos: '.$e->getMessage(), 0, $e);
        } finally {
            if ($image instanceof \Imagick) {
                $image->clear();
                $image->destroy();
            }
        }

        if ($targetAbsolutePath !== $sourceAbsolutePath && File::exists($sourceAbsolutePath)) {
            File::delete($sourceAbsolutePath);
        }

        clearstatcache(true, $targetAbsolutePath);

        return (int) File::size($targetAbsolutePath);
    }
}
