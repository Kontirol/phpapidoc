<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Scanner;

use FilesystemIterator;
use Kontirol\ApiDoc\Exception\ScanException;
use Kontirol\ApiDoc\Support\Path;
use Kontirol\ApiDoc\Support\Str;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Walks the configured controller directories and returns the files worth
 * parsing.
 *
 * The scanner never inspects class names: it only decides *which files* to hand
 * over. Reading the real namespace and class name happens during parsing, where
 * the file content is already tokenised.
 */
final class ControllerScanner
{
    /**
     * @var list<string>
     */
    private $excludePatterns;

    /**
     * @param list<string> $excludePatterns Applied to every source.
     */
    public function __construct(array $excludePatterns = [])
    {
        $this->excludePatterns = $excludePatterns;
    }

    /**
     * @param list<array{path: string, namespace: ?string, prefix: string, exclude: list<string>, suffix: string}> $sources
     *
     * @return list<ControllerFile>
     */
    public function scan(array $sources): array
    {
        $collected = [];

        foreach ($sources as $source) {
            $root = $source['path'];

            if (!is_dir($root)) {
                throw ScanException::directoryNotFound($root);
            }

            if (!is_readable($root)) {
                throw ScanException::notReadable($root);
            }

            $realRoot = realpath($root);

            if ($realRoot !== false) {
                $root = Path::normalize($realRoot);
            }

            $patterns = array_merge($this->excludePatterns, $source['exclude']);
            $found = [];

            foreach ($this->collectFiles($root, $source['suffix'], $patterns) as $path) {
                $found[$path] = new ControllerFile(
                    $path,
                    $root,
                    Path::relative($root, $path),
                    $source['prefix'],
                    $source['namespace']
                );
            }

            if ($found === [] && $source['namespace'] !== null) {
                throw ScanException::empty($root);
            }

            $collected += $found;
        }

        return array_values($collected);
    }

    /**
     * @param list<string> $patterns
     *
     * @return list<string>
     */
    private function collectFiles(string $root, string $suffix, array $patterns): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $filter = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function (SplFileInfo $current) use ($root, $patterns): bool {
                if (!$current->isDir()) {
                    return true;
                }

                $relative = Path::relative($root, $current->getPathname()) . '/';

                return !$this->isExcluded($relative, $patterns);
            }
        );

        $result = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $name = $file->getFilename();

            if (!Str::endsWith($name, '.php') || !Str::endsWith($name, $suffix)) {
                continue;
            }

            $path = Path::normalize($file->getPathname());

            if ($this->isExcluded(Path::relative($root, $path), $patterns)) {
                continue;
            }

            $result[] = $path;
        }

        sort($result);

        return $result;
    }

    /**
     * Exclusion rules, in order of specificity:
     *
     *  - a pattern containing "/" or "*" is matched with fnmatch() against the
     *    path relative to the controller root;
     *  - a bare name matches any directory segment, or a file base name.
     *
     * @param list<string> $patterns
     */
    private function isExcluded(string $relativePath, array $patterns): bool
    {
        $relativePath = ltrim(Path::toUnix($relativePath), '/');

        if ($relativePath === '') {
            return false;
        }

        foreach ($patterns as $pattern) {
            $pattern = trim(Path::toUnix($pattern));

            if ($pattern === '') {
                continue;
            }

            if (strpos($pattern, '/') !== false || strpos($pattern, '*') !== false) {
                if (fnmatch($pattern, $relativePath) || fnmatch($pattern, rtrim($relativePath, '/'))) {
                    return true;
                }

                continue;
            }

            foreach (explode('/', trim($relativePath, '/')) as $segment) {
                if ($segment === $pattern || pathinfo($segment, PATHINFO_FILENAME) === $pattern) {
                    return true;
                }
            }
        }

        return false;
    }
}
