<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Support;

/**
 * Small string helpers.
 *
 * PHP 7.4 compatible replacements for str_starts_with()/str_ends_with()/
 * str_contains() so the package can keep supporting 7.4 while still reading
 * naturally on PHP 8.
 */
final class Str
{
    private function __construct()
    {
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $length = strlen($needle);

        return $length <= strlen($haystack) && substr_compare($haystack, $needle, -$length) === 0;
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }

    /**
     * Splits text into trimmed, non empty lines.
     *
     * @return list<string>
     */
    public static function lines(string $text): array
    {
        $parts = preg_split("/\r\n|\r|\n/", $text);

        if ($parts === false) {
            return [];
        }

        $lines = [];
        foreach ($parts as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Collapses every whitespace run into a single space and trims the result.
     */
    public static function squeeze(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
