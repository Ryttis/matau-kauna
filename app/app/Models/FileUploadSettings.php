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

    private static function defaultChannel(): array
    {
        return [
            'allow_photo' => true,
            'allow_video' => false,
            'max_photo_size_kb' => 5120,
            'max_video_size_kb' => 51200,
            'max_photo_files_per_problem' => 5,
            'max_video_files_per_problem' => 1,
            'max_total_upload_kb' => 51200,
            'description_max_chars' => 65535,
        ];
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

        return array_merge(self::defaultChannel(), $full[$key] ?? []);
    }

    public static function set(array $config): void
    {
        static::query()->updateOrCreate([], ['config' => self::mergeDefaults($config)]);
    }

    public static function resolveUploadChannel(?string $xUploadChannelHeader): string
    {
        return $xUploadChannelHeader === self::CHANNEL_MOBILE ? self::CHANNEL_MOBILE : self::CHANNEL_WEB;
    }

    public static function phpIniLimits(): array
    {
        return [
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),
        ];
    }

    private static function mergeDefaults(array $config): array
    {
        $defaults = self::defaultConfig();

        foreach ([self::CHANNEL_WEB, self::CHANNEL_MOBILE] as $ch) {
            if (! isset($config[$ch]) || ! is_array($config[$ch])) {
                $config[$ch] = $defaults[$ch];
            } else {
                $config[$ch] = array_merge($defaults[$ch], $config[$ch]);
            }
        }

        return $config;
    }

    public static function getChannelForClient(string $channel): array
    {
        $c = self::applyPhpUploadCaps(self::getChannel($channel));

        return array_merge($c, [
            'accept_attr' => self::buildAcceptAttribute($c),
        ]);
    }

    public static function getReporterReportChannelForClient(): array
    {
        $c = self::applyPhpUploadCaps(self::getChannel(self::CHANNEL_WEB));
        $c['allow_video'] = false;
        $c['max_video_files_per_problem'] = 0;

        return array_merge($c, [
            'accept_attr' => self::buildAcceptAttribute($c),
        ]);
    }

    private static function iniFilesizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0') {
            return PHP_INT_MAX;
        }

        if (is_numeric($value)) {
            $n = (int) $value;

            return $n > 0 ? $n : PHP_INT_MAX;
        }

        $metric = strtoupper(substr($value, -1));
        $number = (int) $value;

        return match ($metric) {
            'K' => $number * 1024,
            'M' => $number * 1048576,
            'G' => $number * 1073741824,
            default => $number > 0 ? $number : PHP_INT_MAX,
        };
    }

    private static function applyPhpUploadCaps(array $c): array
    {
        $uploadBytes = self::iniFilesizeToBytes((string) ini_get('upload_max_filesize'));
        $postBytes = self::iniFilesizeToBytes((string) ini_get('post_max_size'));
        $uploadKb = max(1, (int) floor($uploadBytes / 1024));
        $postKb = max(1, (int) floor($postBytes / 1024));
        $postBudgetKb = max(1, $postKb - 64);

        $c['max_photo_size_kb'] = min((int) $c['max_photo_size_kb'], $uploadKb);
        $c['max_video_size_kb'] = min((int) $c['max_video_size_kb'], $uploadKb);
        $c['max_total_upload_kb'] = min((int) $c['max_total_upload_kb'], $postBudgetKb);

        return $c;
    }

    private static function buildAcceptAttribute(array $channelConfig): string
    {
        $parts = [];
        if (! empty($channelConfig['allow_photo'])) {
            $parts[] = 'image/jpeg';
            $parts[] = 'image/png';
        }
        if (! empty($channelConfig['allow_video'])) {
            $parts[] = 'video/mp4';
            $parts[] = 'video/webm';
            $parts[] = 'video/quicktime';
        }

        return implode(',', array_unique($parts));
    }
}
