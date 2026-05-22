<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileUploadSettings extends Model
{
    protected $table = 'file_upload_settings';

    protected $fillable = ['config'];

    protected $casts = [
        'config' => 'array',
    ];

    public const CHANNEL_WEB = 'web';

    public const CHANNEL_MOBILE = 'mobile';

    public const KIND_PHOTO = 'photo';

    public const KIND_VIDEO = 'video';

    private const FORMAT_LIMIT = 20;

    private static function defaultPhotoFormats(): array
    {
        return [
            ['label' => 'JPEG', 'mime' => 'image/jpeg', 'extensions' => ['jpg', 'jpeg']],
            ['label' => 'PNG', 'mime' => 'image/png', 'extensions' => ['png']],
        ];
    }

    private static function defaultVideoFormats(): array
    {
        return [
            ['label' => 'MP4', 'mime' => 'video/mp4', 'extensions' => ['mp4']],
            ['label' => 'WebM', 'mime' => 'video/webm', 'extensions' => ['webm']],
            ['label' => 'MOV', 'mime' => 'video/quicktime', 'extensions' => ['mov']],
        ];
    }

    private static function defaultChannel(): array
    {
        return [
            'allow_photo' => true,
            'allow_video' => false,
            'photo_formats' => self::defaultPhotoFormats(),
            'video_formats' => self::defaultVideoFormats(),
            'max_photo_size_kb' => 5120,
            'max_video_size_kb' => 51200,
            'max_photo_files_per_problem' => 5,
            'max_video_files_per_problem' => 1,
            'max_video_duration_seconds' => 60,
            'description_max_chars' => 65535,
        ];
    }

    public static function allowedMaxVideoDurationSeconds(): array
    {
        return range(10, 60, 5);
    }

    public static function defaultConfig(): array
    {
        return [
            self::CHANNEL_WEB => self::defaultChannel(),
            self::CHANNEL_MOBILE => self::defaultChannel(),
        ];
    }

    public static function getFull(): array
    {
        try {
            $row = static::query()->first();

            return self::mergeDefaults($row?->config ?? []);
        } catch (\Throwable $e) {
            return self::defaultConfig();
        }
    }

    public static function getChannel(string $channel): array
    {
        $full = self::getFull();
        $key = $channel === self::CHANNEL_MOBILE ? self::CHANNEL_MOBILE : self::CHANNEL_WEB;

        return self::normalizeChannelConfig(array_merge(self::defaultChannel(), $full[$key] ?? []));
    }

    public static function set(array $config): void
    {
        static::query()->updateOrCreate([], ['config' => self::mergeDefaults($config)]);
    }

    public static function resolveUploadChannel(?string $xUploadChannelHeader): string
    {
        return $xUploadChannelHeader === self::CHANNEL_MOBILE ? self::CHANNEL_MOBILE : self::CHANNEL_WEB;
    }

    private static function normalizeChannelConfig(array $c): array
    {
        $allowed = self::allowedMaxVideoDurationSeconds();
        $v = (int) ($c['max_video_duration_seconds'] ?? 60);
        $c['max_video_duration_seconds'] = in_array($v, $allowed, true) ? $v : 60;
        $c['max_photo_size_kb'] = max(64, (int) ($c['max_photo_size_kb'] ?? 5120));
        $c['max_video_size_kb'] = max(64, (int) ($c['max_video_size_kb'] ?? 51200));
        $c['photo_formats'] = self::normalizeFormats($c['photo_formats'] ?? null, self::defaultPhotoFormats());
        $c['video_formats'] = self::normalizeFormats($c['video_formats'] ?? null, self::defaultVideoFormats());
        unset($c['max_total_upload_kb'], $c['max_files_per_problem']);

        return $c;
    }

    public static function normalizeFormats(mixed $formats, array $fallback): array
    {
        if (! is_array($formats)) {
            return $fallback;
        }

        $normalized = [];
        $seen = [];
        foreach ($formats as $format) {
            if (! is_array($format)) {
                continue;
            }

            $label = trim((string) ($format['label'] ?? ''));
            $mime = strtolower(trim((string) ($format['mime'] ?? '')));
            $extensions = self::normalizeExtensions($format['extensions'] ?? []);

            if ($label === '' || $mime === '' || $extensions === []) {
                continue;
            }

            if (isset($seen[$mime])) {
                continue;
            }

            $seen[$mime] = true;
            $normalized[] = [
                'label' => $label,
                'mime' => $mime,
                'extensions' => $extensions,
            ];

            if (count($normalized) >= self::FORMAT_LIMIT) {
                break;
            }
        }

        return $normalized === [] ? $fallback : $normalized;
    }

    private static function normalizeExtensions(mixed $extensions): array
    {
        if (is_string($extensions)) {
            $extensions = preg_split('/[\s,]+/', $extensions, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (! is_array($extensions)) {
            return [];
        }

        $normalized = [];
        foreach ($extensions as $extension) {
            $extension = strtolower(trim((string) $extension));
            $extension = ltrim($extension, '.');
            if ($extension === '' || ! preg_match('/^[a-z0-9]{1,12}$/', $extension)) {
                continue;
            }
            $normalized[$extension] = $extension;
        }

        return array_values($normalized);
    }

    private static function mergeDefaults(array $config): array
    {
        $defaults = self::defaultConfig();

        foreach ([self::CHANNEL_WEB, self::CHANNEL_MOBILE] as $ch) {
            if (! isset($config[$ch]) || ! is_array($config[$ch])) {
                $config[$ch] = $defaults[$ch];
            } else {
                $config[$ch] = self::normalizeChannelConfig(array_merge($defaults[$ch], $config[$ch]));
            }
        }

        return $config;
    }

    public static function getChannelForClient(string $channel): array
    {
        $c = self::getChannel($channel);

        return array_merge($c, [
            'accept_attr' => self::buildAcceptAttribute($c),
        ]);
    }

    public static function getReporterReportChannelForClient(): array
    {
        return self::getChannelForClient(self::CHANNEL_WEB);
    }

    public static function getReviewProgressChannelForClient(): array
    {
        $c = self::forcePhotoOnly(self::getChannel(self::CHANNEL_WEB));

        return array_merge($c, [
            'accept_attr' => self::buildAcceptAttribute($c),
        ]);
    }

    public static function getChannelForReviewEntry(string $channel): array
    {
        return self::forcePhotoOnly(self::getChannel($channel));
    }

    private static function forcePhotoOnly(array $c): array
    {
        $c['allow_video'] = false;
        $c['max_video_files_per_problem'] = 0;

        return $c;
    }

    public static function kindForMime(array $channelConfig, string $mime): ?string
    {
        $mime = strtolower(trim($mime));
        if ($mime === '') {
            return null;
        }

        if (in_array($mime, self::formatMimes($channelConfig['photo_formats'] ?? []), true)) {
            return self::KIND_PHOTO;
        }

        if (in_array($mime, self::formatMimes($channelConfig['video_formats'] ?? []), true)) {
            return self::KIND_VIDEO;
        }

        return null;
    }

    public static function extensionAllowedForKind(array $channelConfig, string $kind, string $extension): bool
    {
        $extension = strtolower(ltrim(trim($extension), '.'));
        if ($extension === '') {
            return false;
        }

        $formats = $kind === self::KIND_PHOTO
            ? ($channelConfig['photo_formats'] ?? [])
            : ($channelConfig['video_formats'] ?? []);

        return in_array($extension, self::formatExtensions($formats), true);
    }

    private static function buildAcceptAttribute(array $channelConfig): string
    {
        $parts = [];
        if (! empty($channelConfig['allow_photo'])) {
            array_push($parts, ...self::formatMimes($channelConfig['photo_formats'] ?? []));
        }
        if (! empty($channelConfig['allow_video'])) {
            array_push($parts, ...self::formatMimes($channelConfig['video_formats'] ?? []));
        }

        return implode(',', array_unique($parts));
    }

    private static function formatMimes(array $formats): array
    {
        return collect($formats)
            ->map(fn ($format) => strtolower(trim((string) ($format['mime'] ?? ''))))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function formatExtensions(array $formats): array
    {
        $extensions = [];
        foreach ($formats as $format) {
            foreach (self::normalizeExtensions($format['extensions'] ?? []) as $extension) {
                $extensions[$extension] = $extension;
            }
        }

        return array_values($extensions);
    }
}
