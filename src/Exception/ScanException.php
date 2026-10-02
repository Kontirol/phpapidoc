<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Exception;

/**
 * Raised when the controller scan step fails.
 */
final class ScanException extends ApiDocException
{
    public static function directoryNotFound(string $path): self
    {
        return new self('Controller directory not found: ' . $path, ['path' => $path]);
    }

    public static function notReadable(string $path): self
    {
        return new self('Controller directory is not readable: ' . $path, ['path' => $path]);
    }

    public static function empty(string $path): self
    {
        return new self(
            'No controller class was found in: ' . $path . ' (check "controllers" and "scan.suffix" in your config)',
            ['path' => $path]
        );
    }

    public static function unreadableFile(string $path): self
    {
        return new self('Unable to read controller file: ' . $path, ['file' => $path]);
    }
}
