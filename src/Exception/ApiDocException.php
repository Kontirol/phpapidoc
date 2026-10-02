<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Exception;

use RuntimeException;
use Throwable;

/**
 * Base class for every exception thrown by ApiDoc.
 *
 * It carries an optional context bag (file, line, controller, tag, ...) that
 * the console layer renders into a readable and actionable error message.
 *
 * @phpstan-consistent-constructor
 */
class ApiDocException extends RuntimeException
{
    /** @var array<string, mixed> */
    private $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = '', array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->context = $context;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return static
     */
    public static function create(string $message, array $context = [], ?Throwable $previous = null)
    {
        return new static($message, $context, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Returns a copy of the exception with additional context attached.
     *
     * @param array<string, mixed> $context
     *
     * @return static
     */
    public function withContext(array $context)
    {
        $copy = clone $this;
        $copy->context = array_merge($this->context, $context);

        return $copy;
    }

    /**
     * Human readable, multi line rendering of the message plus its context.
     */
    public function getDetailedMessage(): string
    {
        $lines = [$this->getMessage()];

        $file = $this->context['file'] ?? null;
        $line = $this->context['line'] ?? null;

        if (is_string($file) && $file !== '') {
            $suffix = is_scalar($line) ? ':' . $line : '';
            $lines[] = '  at ' . $file . $suffix;
        }

        foreach ($this->context as $key => $value) {
            if ($key === 'file' || $key === 'line' || !is_scalar($value)) {
                continue;
            }

            $lines[] = '  ' . $key . ': ' . $value;
        }

        return implode(PHP_EOL, $lines);
    }
}
