<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Cli;

/**
 * A tiny option parser: no dependency, no exotic syntax.
 *
 * Supported forms: "generate", "-c path", "-c=path", "--config path",
 * "--config=path", "--flag", "-v". Bundled short options ("-vv") are
 * deliberately not supported, the long form is always available instead.
 */
final class Arguments
{
    /**
     * @var list<string>
     */
    private $arguments;

    /**
     * @var array<string, string|bool>
     */
    private $options;

    /**
     * @param list<string>               $arguments
     * @param array<string, string|bool> $options
     */
    private function __construct(array $arguments, array $options)
    {
        $this->arguments = $arguments;
        $this->options = $options;
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $expectsValue Option names that consume the next token.
     */
    public static function parse(array $tokens, array $expectsValue = []): self
    {
        $arguments = [];
        $options = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($token === '--') {
                for ($rest = $index + 1; $rest < $count; $rest++) {
                    $arguments[] = $tokens[$rest];
                }

                break;
            }

            if (!self::looksLikeAnOption($token)) {
                $arguments[] = $token;

                continue;
            }

            $name = ltrim($token, '-');
            $value = true;

            $separator = strpos($name, '=');

            if ($separator !== false) {
                $value = substr($name, $separator + 1);
                $name = substr($name, 0, $separator);
            } elseif (in_array($name, $expectsValue, true) && $index + 1 < $count) {
                $value = $tokens[++$index];
            }

            $options[$name] = $value;
        }

        return new self($arguments, $options);
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function command(): ?string
    {
        return $this->arguments[0] ?? null;
    }

    /**
     * Returns the value of an option, or null when it was not given.
     */
    public function option(string $name): ?string
    {
        $value = $this->options[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * True when the option was present at all, with or without a value.
     */
    public function flag(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    private static function looksLikeAnOption(string $token): bool
    {
        return strlen($token) > 1 && $token[0] === '-' && !is_numeric($token);
    }
}
