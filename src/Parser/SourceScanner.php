<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Parser;

/**
 * Extracts classes, methods and their docblocks from PHP source code.
 *
 * Only tokens are inspected and the class is never loaded, which keeps the
 * generator usable in projects whose dependencies are not installed and makes
 * it safe to run on code that would fail at runtime.
 */
final class SourceScanner
{
    /**
     * @return list<ParsedClass>
     */
    public function scan(string $code): array
    {
        $tokens = token_get_all($code);

        /** @var list<ParsedClass> $classes */
        $classes = [];

        /** @var list<array{depth: int, class: ?ParsedClass}> $stack */
        $stack = [];

        $namespace = '';
        $current = null;
        $depth = 0;

        /** @var array{text: string, line: int}|null $pending */
        $pending = null;

        $visibility = 'public';
        $isStatic = false;
        $isAbstract = false;
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_string($token)) {
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;

                    while ($stack !== [] && $depth <= $stack[count($stack) - 1]['depth']) {
                        array_pop($stack);
                    }

                    $current = $stack === [] ? null : $stack[count($stack) - 1]['class'];
                }

                continue;
            }

            $id = $token[0];

            if ($id === T_NAMESPACE) {
                $namespace = $this->readNamespace($tokens, $index);
                $pending = null;

                continue;
            }

            if ($id === T_DOC_COMMENT) {
                $pending = ['text' => $token[1], 'line' => $token[2]];

                continue;
            }

            if ($id === T_CLASS || $id === T_INTERFACE || $id === T_TRAIT) {
                $name = $this->nextString($tokens, $index);
                $class = null;

                if ($name !== null && $this->previousMeaningfulToken($tokens, $index) !== T_DOUBLE_COLON) {
                    $class = new ParsedClass($name, $namespace, $token[2]);
                    $classes[] = $class;
                }

                $stack[] = ['depth' => $depth, 'class' => $class];
                $current = $class;
                $pending = null;
                $visibility = 'public';

                continue;
            }

            if ($id === T_PUBLIC || $id === T_PROTECTED || $id === T_PRIVATE) {
                $visibility = strtolower(trim($token[1]));

                continue;
            }

            if ($id === T_STATIC) {
                $isStatic = true;

                continue;
            }

            if ($id === T_ABSTRACT) {
                $isAbstract = true;

                continue;
            }

            if ($id === T_FUNCTION) {
                $name = $this->nextString($tokens, $index);

                if ($name !== null && $current !== null) {
                    $method = new ParsedMethod($name, $token[2]);
                    $method->visibility = $visibility;
                    $method->isStatic = $isStatic;
                    $method->isAbstract = $isAbstract;

                    if ($pending !== null) {
                        $method->docBlock = $pending['text'];
                        $method->line = $pending['line'];
                    }

                    $current->addMethod($method);
                }

                $pending = null;
                $visibility = 'public';
                $isStatic = false;
                $isAbstract = false;

                continue;
            }

            if ($id === T_VARIABLE || $id === T_CONST || $id === T_USE) {
                // Property, constant or trait usage: the pending docblock was
                // attached to it, not to a method.
                $pending = null;
                $visibility = 'public';
            }
        }

        return $classes;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function readNamespace(array $tokens, int $index): string
    {
        $namespace = '';
        $qualified = defined('T_NAME_QUALIFIED') ? (int) constant('T_NAME_QUALIFIED') : -1;
        $count = count($tokens);

        for ($i = $index + 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }

                continue;
            }

            [$id, $text] = $token;

            if ($id === T_STRING || $id === T_NS_SEPARATOR) {
                $namespace .= $text;

                continue;
            }

            if ($qualified > 0 && $id === $qualified) {
                $namespace = $text;

                continue;
            }

            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }

            break;
        }

        return trim($namespace, '\\');
    }

    /**
     * Reads the identifier right after a "class" or "function" keyword.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function nextString(array $tokens, int $index): ?string
    {
        $count = count($tokens);

        for ($i = $index + 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '&') {
                    continue;
                }

                return null;
            }

            [$id, $text] = $token;

            if ($id === T_STRING) {
                return $text;
            }

            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_NS_SEPARATOR) {
                continue;
            }

            return null;
        }

        return null;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function previousMeaningfulToken(array $tokens, int $index): int
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (is_string($token)) {
                return -1;
            }

            $id = $token[0];

            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }

            return $id;
        }

        return -1;
    }
}
