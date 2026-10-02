<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Support;

/**
 * Cross platform path helpers (Windows and *nix alike).
 */
final class Path
{
    private function __construct()
    {
    }

    public static function isAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }

    /**
     * Resolves a possibly relative path against a base directory.
     */
    public static function resolve(string $basePath, string $path): string
    {
        if (self::isAbsolute($path)) {
            return self::normalize($path);
        }

        return self::normalize(rtrim($basePath, "/\\") . DIRECTORY_SEPARATOR . $path);
    }

    /**
     * Normalises directory separators for the current platform and collapses
     * "." and ".." segments, so that paths printed to the console stay short.
     */
    public static function normalize(string $path): string
    {
        $separator = DIRECTORY_SEPARATOR;

        $path = $separator === '\\' ? str_replace('/', '\\', $path) : str_replace('\\', '/', $path);

        if ($path === '') {
            return '';
        }

        // Keep whatever prefix means "root": a Windows drive or a leading slash.
        $prefix = '';

        if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            $prefix = substr($path, 0, 3);
            $path = substr($path, 3);
        } elseif ($path[0] === '/' || $path[0] === '\\') {
            $prefix = $separator;
            $path = ltrim($path, '/\\');
        }

        $segments = [];

        foreach (explode($separator, $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments !== [] && end($segments) !== '..') {
                    array_pop($segments);

                    continue;
                }

                // A ".." right after the root cannot be collapsed any further.
                if ($prefix !== '') {
                    continue;
                }
            }

            $segments[] = $segment;
        }

        return $prefix . implode($separator, $segments);
    }

    /**
     * Converts a path to forward slashes, for stable comparisons and output.
     */
    public static function toUnix(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Compares two paths ignoring separator and trailing slash differences.
     */
    public static function equals(string $a, string $b): bool
    {
        $normalize = static function (string $path): string {
            return rtrim(self::toUnix($path), '/');
        };

        return $normalize($a) === $normalize($b);
    }

    /**
     * True when $path lives inside (or is) $root.
     */
    public static function isWithin(string $path, string $root): bool
    {
        $path = rtrim(self::toUnix($path), '/');
        $root = rtrim(self::toUnix($root), '/');

        return $path === $root || strpos($path . '/', $root . '/') === 0;
    }

    /**
     * Returns $path relative to $basePath, or $path itself when not inside it.
     */
    public static function relative(string $basePath, string $path): string
    {
        $basePath = rtrim(self::toUnix($basePath), '/');
        $path = self::toUnix($path);

        if (strpos($path, $basePath . '/') === 0) {
            return substr($path, strlen($basePath) + 1);
        }

        return $path;
    }
}
