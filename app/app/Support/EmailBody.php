<?php

namespace App\Support;

final class EmailBody
{
    public static function replacePlaceholders(string $text, array $values): string
    {
        foreach ($values as $key => $value) {
            $text = str_replace('{'.$key.'}', (string) $value, $text);
        }

        return $text;
    }

    public static function markdownConverterOptions(): array
    {
        return [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ];
    }
}
