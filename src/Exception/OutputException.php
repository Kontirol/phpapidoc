<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Exception;

/**
 * Raised when the generated document cannot be turned into an output file.
 */
final class OutputException extends ApiDocException
{
    public static function unwritable(string $path, string $reason): self
    {
        return new self('Unable to write output file: ' . $reason, ['file' => $path]);
    }

    public static function unsupportedFormat(string $format, array $supported): self
    {
        return new self(
            sprintf('Unsupported output format "%s". Supported: %s.', $format, implode(', ', $supported)),
            ['format' => $format]
        );
    }

    public static function encodeFailed(string $format, string $reason): self
    {
        return new self(sprintf('Unable to encode the document as %s: %s', $format, $reason));
    }

    public static function emptyDocument(): self
    {
        return new self(
            'No endpoint was collected. Nothing to write. '
            . 'Check your controller path, the scan suffix and the @route annotations.'
        );
    }
}
