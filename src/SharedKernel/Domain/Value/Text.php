<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Value;

/**
 * Normalises free text typed by people before it becomes part of the model.
 */
final class Text
{
    /**
     * For one-line values such as names: no control or invisible characters (zero-width
     * spaces, direction marks…), and any run of whitespace becomes a single space.
     */
    public static function singleLine(string $value): string
    {
        $value = (string) preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', self::validUtf8($value));

        return trim((string) preg_replace('/[\s\p{Z}]+/u', ' ', $value));
    }

    /**
     * For multi-line values such as a CV: line breaks become \n (browsers send \r\n, which
     * would count twice against a length limit), and control characters other than tabs and
     * line breaks are removed. Everything else is kept as written.
     */
    public static function multiLine(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", self::validUtf8($value));

        return trim((string) preg_replace('/[^\P{Cc}\t\n]+/u', '', $value));
    }

    private static function validUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
