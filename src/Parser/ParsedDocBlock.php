<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Parser;

/**
 * The tags found in a single docblock, in source order.
 */
final class ParsedDocBlock
{
    /**
     * @var array<string, list<TagValue>> Lower case tag name => occurrences.
     */
    private $tags = [];

    public function add(TagValue $tag): void
    {
        $this->tags[$tag->name][] = $tag;
    }

    public function has(string $name): bool
    {
        return isset($this->tags[strtolower($name)]);
    }

    public function first(string $name): ?TagValue
    {
        $name = strtolower($name);

        return $this->tags[$name][0] ?? null;
    }

    /**
     * @return list<TagValue>
     */
    public function all(string $name): array
    {
        $name = strtolower($name);

        return $this->tags[$name] ?? [];
    }

    /**
     * Trimmed value of the first occurrence, or $default when absent.
     */
    public function value(string $name, string $default = ''): string
    {
        $tag = $this->first($name);

        if ($tag === null) {
            return $default;
        }

        $value = trim($tag->value);

        return $value === '' ? $default : $value;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->tags);
    }

    public function isEmpty(): bool
    {
        return $this->tags === [];
    }
}
