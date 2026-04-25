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
        unset($c['max_video_size_kb'], $c['max_total_upload_kb'], $c['max_files_per_problem']);

        return $c;
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
        $c = self::getChannel(self::CHANNEL_WEB);
        $c['allow_video'] = false;
        $c['max_video_files_per_problem'] = 0;

        return array_merge($c, [
            'accept_attr' => self::buildAcceptAttribute($c),
        ]);
    }

    public static function getReviewProgressChannelForClient(): array
    {
        $c = self::getChannel(self::CHANNEL_WEB);
        $c['allow_video'] = false;
        $c['max_video_files_per_problem'] = 0;

        return array_merge($c, [
            'accept_attr' => self::buildAcceptAttribute($c),
        ]);
    }

    public static function getChannelForReviewEntry(string $channel): array
    {
        $c = self::getChannel($channel);
        $c['allow_video'] = false;
        $c['max_video_files_per_problem'] = 0;

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
