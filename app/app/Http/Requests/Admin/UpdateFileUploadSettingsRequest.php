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
                $prefix.'.max_photo_files_per_problem' => ['integer', 'min:0', 'max:50'],
                $prefix.'.max_video_files_per_problem' => ['integer', 'min:0', 'max:20'],
                $prefix.'.max_video_duration_seconds' => ['integer', 'in:'.implode(',', FileUploadSettings::allowedMaxVideoDurationSeconds())],
                $prefix.'.description_max_chars' => ['integer', 'min:1000', 'max:65535'],
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
        }

        $this->merge(['config' => $config]);
    }
}
