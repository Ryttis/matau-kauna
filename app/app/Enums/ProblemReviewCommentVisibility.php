<?php

namespace App\Enums;

enum ProblemReviewCommentVisibility: string
{
    case Public = 'public';
    case Internal = 'internal';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
