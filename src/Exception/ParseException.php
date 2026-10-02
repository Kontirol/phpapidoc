<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Exception;

/**
 * Raised when a docblock cannot be parsed into a valid endpoint definition.
 */
final class ParseException extends ApiDocException
{
    public static function invalidTag(string $tag, string $usage, string $file, int $line): self
    {
        return new self(
            sprintf('Invalid @%s tag. Expected: %s', $tag, $usage),
            ['file' => $file, 'line' => $line, 'tag' => $tag]
        );
    }

    public static function invalidJson(string $tag, string $reason, string $file, int $line): self
    {
        return new self(
            sprintf('Invalid JSON in @%s tag: %s', $tag, $reason),
            ['file' => $file, 'line' => $line, 'tag' => $tag]
        );
    }

    public static function notABodyTag(string $tag, string $usage, string $file, int $line): self
    {
        return new self(
            sprintf('Malformed @%s tag. Expected: %s', $tag, $usage),
            ['file' => $file, 'line' => $line, 'tag' => $tag]
        );
    }
}
