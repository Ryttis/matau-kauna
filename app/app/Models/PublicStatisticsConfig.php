<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicStatisticsConfig extends Model
{
    protected $table = 'public_statistics_config';

    protected $fillable = ['config'];

    protected $casts = [
        'config' => 'array',
    ];

    private static function defaultConfig(): array
    {
        return [
            'kpis' => ['received_reports', 'avg_resolution_days'],
            'date_from' => null,
            'date_to' => null,
            'show_category_filter' => true,
        ];
    }

    public static function get(): array
    {
        try {
            $row = static::query()->first();

            return $row?->config ?? self::defaultConfig();
        } catch (\Throwable $e) {
            return self::defaultConfig();
        }
    }

    public static function set(array $config): void
    {
        static::query()->updateOrCreate([], ['config' => $config]);
    }
}
