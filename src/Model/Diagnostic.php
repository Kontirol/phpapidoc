<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Model;

/**
 * A problem detected while scanning or parsing.
 *
 * Diagnostics are collected instead of thrown immediately, so a single run can
 * report every issue at once, the way a compiler does. Errors are fatal for the
 * run, warnings are not.
 */
final class Diagnostic
{
    public const LEVEL_NOTICE = 'notice';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    /**
     * @var string One of the LEVEL_* constants.
     */
    public $level;

    /**
     * @var string Machine readable code, e.g. "route.missing" or "param.unknown".
     */
    public $code;

    /**
     * @var string
     */
    public $message;

    /**
     * @var string|null
     */
    public $file;

    /**
     * @var int|null
     */
    public $line;

    /**
     * @var array<string, mixed>
     */
    public $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $level,
        string $code,
        string $message,
        ?string $file = null,
        ?int $line = null,
        array $context = []
    ) {
        $this->level = $level;
        $this->code = $code;
        $this->message = $message;
        $this->file = $file;
        $this->line = $line;
        $this->context = $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(
        string $code,
        string $message,
        ?string $file = null,
        ?int $line = null,
        array $context = []
    ): self {
        return new self(self::LEVEL_ERROR, $code, $message, $file, $line, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(
        string $code,
        string $message,
        ?string $file = null,
        ?int $line = null,
        array $context = []
    ): self {
        return new self(self::LEVEL_WARNING, $code, $message, $file, $line, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function notice(
        string $code,
        string $message,
        ?string $file = null,
        ?int $line = null,
        array $context = []
    ): self {
        return new self(self::LEVEL_NOTICE, $code, $message, $file, $line, $context);
    }

    public function isError(): bool
    {
        return $this->level === self::LEVEL_ERROR;
    }

    public function isWarning(): bool
    {
        return $this->level === self::LEVEL_WARNING;
    }

    public function location(): string
    {
        if ($this->file === null) {
            return '';
        }

        return $this->line !== null ? $this->file . ':' . $this->line : $this->file;
    }

    public function __toString(): string
    {
        $location = $this->location();

        return sprintf(
            '[%s] %s%s',
            strtoupper($this->level),
            $this->message,
            $location === '' ? '' : ' (' . $location . ')'
        );
    }
}
