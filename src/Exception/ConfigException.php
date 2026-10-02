<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Exception;

/**
 * Raised when the configuration file is missing, unreadable or invalid.
 */
final class ConfigException extends ApiDocException
{
    public static function fileNotFound(string $path): self
    {
        return new self('Configuration file not found: ' . $path, ['file' => $path]);
    }

    public static function unreadable(string $path, string $reason): self
    {
        return new self('Unable to read configuration file: ' . $reason, ['file' => $path]);
    }

    public static function unsupportedFormat(string $extension, array $supported): self
    {
        return new self(
            sprintf(
                'Unsupported configuration format "%s". Supported: %s.',
                $extension,
                implode(', ', $supported)
            ),
            ['extension' => $extension]
        );
    }

    public static function invalid(string $message): self
    {
        return new self($message);
    }
}
