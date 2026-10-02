<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\OpenApi;

/**
 * Derives a JSON Schema from a decoded example payload.
 *
 * The generator never guesses beyond what the example shows: it describes the
 * exact shape of the sample, which is what documentation consumers want.
 */
final class SchemaInferrer
{
    /**
     * @param mixed $value
     *
     * @return array<string, mixed>
     */
    public function infer($value): array
    {
        if ($value === null) {
            return ['nullable' => true];
        }

        if (is_bool($value)) {
            return ['type' => 'boolean'];
        }

        if (is_int($value)) {
            return ['type' => 'integer'];
        }

        if (is_float($value)) {
            return ['type' => 'number'];
        }

        if (is_string($value)) {
            return ['type' => 'string'];
        }

        if (!is_array($value)) {
            return ['type' => 'string'];
        }

        if ($value === []) {
            // json_decode() cannot tell [] from {} in associative mode, so an
            // empty payload is documented as an empty array.
            return ['type' => 'array', 'items' => ['type' => 'string']];
        }

        if (self::isList($value)) {
            return [
                'type' => 'array',
                'items' => $this->infer($value[0]),
            ];
        }

        $properties = [];

        foreach ($value as $key => $item) {
            $properties[(string) $key] = $this->infer($item);
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * @param array<int|string, mixed> $value
     */
    public static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
