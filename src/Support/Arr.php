<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Support;

/**
 * Array helpers used by the configuration layer.
 */
final class Arr
{
    private function __construct()
    {
    }

    /**
     * Dot notation lookup, e.g. Arr::get($data, 'scan.suffix').
     *
     * @param array<int|string, mixed> $array
     * @param mixed                    $default
     *
     * @return mixed
     */
    public static function get(array $array, string $key, $default = null)
    {
        if ($key === '') {
            return $array;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $current = $array;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param array<int|string, mixed> $array
     */
    public static function has(array $array, string $key): bool
    {
        $sentinel = new \stdClass();

        return self::get($array, $key, $sentinel) !== $sentinel;
    }

    /**
     * Recursive merge where the override wins.
     *
     * Associative arrays are merged key by key, everything else (lists, scalars,
     * null) replaces the default value. An explicit null therefore disables a
     * default entry instead of keeping it.
     *
     * @param array<int|string, mixed> $base
     * @param array<int|string, mixed> $override
     *
     * @return array<int|string, mixed>
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (!array_key_exists($key, $base)) {
                $base[$key] = $value;

                continue;
            }

            if (is_array($base[$key]) && is_array($value) && self::isAssoc($base[$key]) && self::isAssoc($value)) {
                $base[$key] = self::merge($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param array<int|string, mixed> $array
     */
    public static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @param array<int|string, mixed> $array
     */
    public static function firstKey(array $array): ?string
    {
        foreach (array_keys($array) as $key) {
            return (string) $key;
        }

        return null;
    }
}
