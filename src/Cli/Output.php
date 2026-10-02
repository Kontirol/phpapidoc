<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Cli;

/**
 * Minimal terminal writer with three verbosity levels.
 *
 * The streams are injectable so the tests can capture what was written.
 */
final class Output
{
    public const QUIET = 0;
    public const NORMAL = 1;
    public const VERBOSE = 2;

    /**
     * @var int
     */
    private $verbosity;

    /**
     * @var resource
     */
    private $stdout;

    /**
     * @var resource
     */
    private $stderr;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct(int $verbosity = self::NORMAL, $stdout = null, $stderr = null)
    {
        $this->verbosity = $verbosity;
        $this->stdout = $stdout === null ? STDOUT : $stdout;
        $this->stderr = $stderr === null ? STDERR : $stderr;
    }

    public function verbosity(): int
    {
        return $this->verbosity;
    }

    public function setVerbosity(int $verbosity): void
    {
        $this->verbosity = $verbosity;
    }

    public function line(string $message = ''): void
    {
        if ($this->verbosity < self::NORMAL) {
            return;
        }

        fwrite($this->stdout, $message . PHP_EOL);
    }

    /**
     * Only printed when the user asked for -v.
     */
    public function verbose(string $message): void
    {
        if ($this->verbosity < self::VERBOSE) {
            return;
        }

        fwrite($this->stdout, $message . PHP_EOL);
    }

    public function error(string $message): void
    {
        fwrite($this->stderr, $message . PHP_EOL);
    }

    /**
     * @param list<string> $lines
     */
    public function listing(array $lines, string $indent = '  '): void
    {
        foreach ($lines as $line) {
            $this->line($indent . $line);
        }
    }
}
