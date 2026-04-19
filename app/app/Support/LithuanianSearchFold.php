<?php

namespace App\Support;

final class LithuanianSearchFold
{
    public static function diacriticToAscii(): array
    {
        return [
            'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i',
            'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
        ];
    }

    public static function fold(string $value): string
    {
        $lower = mb_strtolower($value, 'UTF-8');

        return strtr($lower, self::diacriticToAscii());
    }

    public static function sqlLowerAndFoldColumn(string $column): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException('Invalid SQL column identifier.');
        }
        $expr = 'LOWER('.$column.')';
        foreach (self::diacriticToAscii() as $from => $to) {
            $fromSql = str_replace("'", "''", $from);
            $toSql = str_replace("'", "''", $to);
            $expr = "REPLACE({$expr}, '{$fromSql}', '{$toSql}')";
        }

        return $expr;
    }
}
