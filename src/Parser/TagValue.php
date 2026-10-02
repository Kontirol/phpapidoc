<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Parser;

/**
 * One "@tag value" occurrence inside a docblock.
 *
 * Multi line values are folded back into a single string: continuation lines
 * are appended separated by a newline.
 */
final class TagValue
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $value;

    /**
     * @var int Absolute line number in the source file.
     */
    public $line;

    /**
     * @var string The raw line as written, useful for error messages.
     */
    public $raw;

    public function __construct(string $name, string $value, int $line, string $raw = '')
    {
        $this->name = strtolower($name);
        $this->value = $value;
        $this->line = $line;
        $this->raw = $raw === '' ? '@' . $name . ' ' . $value : $raw;
    }

    /**
     * Appends a continuation line (a wrapped value).
     */
    public function append(string $line): void
    {
        $this->value = trim($this->value . "\n" . $line);
    }

    public function isEmpty(): bool
    {
        return trim($this->value) === '';
    }

    /**
     * Whitespace separated parts of the value.
     *
     * @return list<string>
     */
    public function parts(): array
    {
        $parts = preg_split('/\s+/', trim($this->value), -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [] : $parts;
    }

    /**
     * Value parts for the range [start, count).
     *
     * @return list<string>
     */
    public function slice(int $start, ?int $count = null): array
    {
        $parts = $this->parts();

        if ($count === null) {
            return array_slice($parts, $start);
        }

        return array_slice($parts, $start, $count);
    }

    /**
     * Parses tag values that must contain a JSON payload.
     *
     * @return array{ok: bool, value: mixed, error: string}
     */
    public function asJson(): array
    {
        $raw = trim($this->value);

        if ($raw === '') {
            return ['ok' => false, 'value' => null, 'error' => 'empty payload'];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'value' => null, 'error' => json_last_error_msg()];
        }

        return ['ok' => true, 'value' => $decoded, 'error' => ''];
    }
}
