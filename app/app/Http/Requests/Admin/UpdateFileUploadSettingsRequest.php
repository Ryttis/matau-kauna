<?php

namespace App\Http\Requests\Admin;

use App\Models\FileUploadSettings;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFileUploadSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $channelRules = function (string $prefix) {
            return [
                $prefix => ['required', 'array'],
                $prefix.'.allow_photo' => ['boolean'],
                $prefix.'.allow_video' => ['boolean'],
                $prefix.'.max_photo_size_kb' => ['integer', 'min:64', 'max:102400'],
                $prefix.'.max_video_size_kb' => ['integer', 'min:64', 'max:102400'],
                $prefix.'.max_photo_files_per_problem' => ['integer', 'min:0', 'max:50'],
                $prefix.'.max_video_files_per_problem' => ['integer', 'min:0', 'max:20'],
                $prefix.'.max_video_duration_seconds' => ['integer', 'in:'.implode(',', FileUploadSettings::allowedMaxVideoDurationSeconds())],
                $prefix.'.description_max_chars' => ['integer', 'min:1000', 'max:65535'],
                $prefix.'.photo_formats' => ['array', 'max:20'],
                $prefix.'.photo_formats.*.label' => ['required_with:'.$prefix.'.photo_formats', 'string', 'max:40'],
                $prefix.'.photo_formats.*.mime' => ['required_with:'.$prefix.'.photo_formats', 'string', 'max:100', 'regex:/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i'],
                $prefix.'.photo_formats.*.extensions' => ['required_with:'.$prefix.'.photo_formats', 'array', 'min:1', 'max:10'],
                $prefix.'.photo_formats.*.extensions.*' => ['string', 'max:12', 'regex:/^[a-z0-9]+$/i'],
                $prefix.'.video_formats' => ['array', 'max:20'],
                $prefix.'.video_formats.*.label' => ['required_with:'.$prefix.'.video_formats', 'string', 'max:40'],
                $prefix.'.video_formats.*.mime' => ['required_with:'.$prefix.'.video_formats', 'string', 'max:100', 'regex:/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i'],
                $prefix.'.video_formats.*.extensions' => ['required_with:'.$prefix.'.video_formats', 'array', 'min:1', 'max:10'],
                $prefix.'.video_formats.*.extensions.*' => ['string', 'max:12', 'regex:/^[a-z0-9]+$/i'],
            ];
        };

        return array_merge(
            [
                'config' => ['required', 'array'],
            ],
            $channelRules('config.'.FileUploadSettings::CHANNEL_WEB),
            $channelRules('config.'.FileUploadSettings::CHANNEL_MOBILE)
        );
    }

    protected function prepareForValidation(): void
    {
        $config = $this->input('config');
        if (! is_array($config)) {
            return;
        }

        foreach ([FileUploadSettings::CHANNEL_WEB, FileUploadSettings::CHANNEL_MOBILE] as $ch) {
            if (! isset($config[$ch]) || ! is_array($config[$ch])) {
                continue;
            }
            foreach (['allow_photo', 'allow_video'] as $boolKey) {
                if (array_key_exists($boolKey, $config[$ch])) {
                    $config[$ch][$boolKey] = filter_var(
                        $config[$ch][$boolKey],
                        FILTER_VALIDATE_BOOLEAN,
                        FILTER_NULL_ON_FAILURE
                    ) ?? false;
                }
            }
            foreach (['photo_formats', 'video_formats'] as $formatKey) {
                if (! array_key_exists($formatKey, $config[$ch])) {
                    $config[$ch][$formatKey] = FileUploadSettings::defaultConfig()[$ch][$formatKey];
                    continue;
                }

                $config[$ch][$formatKey] = $this->normalizeFormatRows($config[$ch][$formatKey]);
            }
        }

        $this->merge(['config' => $config]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $config = $this->input('config', []);
            foreach ([FileUploadSettings::CHANNEL_WEB, FileUploadSettings::CHANNEL_MOBILE] as $ch) {
                $channel = is_array($config[$ch] ?? null) ? $config[$ch] : [];
                if (! empty($channel['allow_photo']) && empty($channel['photo_formats'])) {
                    $validator->errors()->add("config.{$ch}.photo_formats", 'Pasirinkite bent vieną nuotraukų formatą.');
                }
                if (! empty($channel['allow_video']) && empty($channel['video_formats'])) {
                    $validator->errors()->add("config.{$ch}.video_formats", 'Pasirinkite bent vieną vaizdo formatą.');
                }
            }
        });
    }

    private function normalizeFormatRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $mime = strtolower(trim((string) ($row['mime'] ?? '')));
            $extensions = $this->normalizeExtensions($row['extensions'] ?? []);

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

            if (count($normalized) >= 20) {
                break;
            }
        }

        return $normalized;
    }

    private function normalizeExtensions(mixed $extensions): array
    {
        if (is_string($extensions)) {
            $extensions = preg_split('/[\s,]+/', $extensions, -1, PREG_SPLIT_NO_EMPTY);
        }
        if (! is_array($extensions)) {
            return [];
        }

        $normalized = [];
        foreach ($extensions as $extension) {
            $extension = strtolower(ltrim(trim((string) $extension), '.'));
            if ($extension !== '' && preg_match('/^[a-z0-9]{1,12}$/i', $extension)) {
                $normalized[$extension] = $extension;
            }
        }

        return array_values($normalized);
    }
}
