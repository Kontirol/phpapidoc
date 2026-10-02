<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Exception;

/**
 * Raised when routes cannot be collected from the configured source.
 */
final class RouteException extends ApiDocException
{
    public static function unknownSource(string $source, array $supported): self
    {
        return new self(
            sprintf('Unknown route source "%s". Supported: %s.', $source, implode(', ', $supported)),
            ['source' => $source]
        );
    }

    public static function bootstrapFailed(string $path, string $reason): self
    {
        $message = 'Unable to bootstrap the framework to read its route table';

        if ($path !== '') {
            $message .= ' (' . $path . ')';
        }

        return new self($message . ': ' . $reason, ['file' => $path]);
    }

    public static function frameworkMissing(string $class): self
    {
        return new self(
            sprintf('Framework class "%s" was not found. Is the host application installed?', $class),
            ['class' => $class]
        );
    }
}
