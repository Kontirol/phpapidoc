<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Cli;

use Kontirol\ApiDoc\Cli\Command\GenerateCommand;
use Kontirol\ApiDoc\Cli\Command\ValidateCommand;
use Kontirol\ApiDoc\Exception\ApiDocException;
use Throwable;

/**
 * Command line entry point.
 */
final class Console
{
    public const VERSION = '0.1.0';

    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const DIAGNOSTICS = 2;

    /**
     * @var Output
     */
    private $output;

    public function __construct(?Output $output = null)
    {
        $this->output = $output === null ? new Output() : $output;
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $arguments = Arguments::parse(array_slice($argv, 1), ['c', 'config']);

        $this->output->setVerbosity($this->verbosity($arguments));

        if ($arguments->flag('help') || $arguments->flag('h')) {
            $this->help();

            return self::SUCCESS;
        }

        if ($arguments->flag('version') || $arguments->flag('V')) {
            $this->output->line('apidoc ' . self::VERSION);

            return self::SUCCESS;
        }

        $command = $arguments->command();

        if ($command === null) {
            $this->help();

            return self::SUCCESS;
        }

        try {
            switch ($command) {
                case 'generate':
                    $this->output->line('apidoc ' . self::VERSION);

                    return (new GenerateCommand($this->output))->execute($arguments);

                case 'validate':
                    $this->output->line('apidoc ' . self::VERSION);

                    return (new ValidateCommand($this->output))->execute($arguments);

                case 'version':
                    $this->output->line('apidoc ' . self::VERSION);

                    return self::SUCCESS;

                case 'help':
                    $this->help();

                    return self::SUCCESS;
            }
        } catch (ApiDocException $exception) {
            $this->output->error('error: ' . $exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->output->error('error: ' . $exception->getMessage());

            if ($this->output->verbosity() >= Output::VERBOSE) {
                $this->output->error($exception->getTraceAsString());
            }

            return self::FAILURE;
        }

        $this->output->error(sprintf('Unknown command "%s".', $command));
        $this->output->line();
        $this->help();

        return self::FAILURE;
    }

    public function output(): Output
    {
        return $this->output;
    }

    public function help(): void
    {
        foreach ($this->helpLines() as $line) {
            $this->output->line($line);
        }
    }

    private function verbosity(Arguments $arguments): int
    {
        if ($arguments->flag('quiet') || $arguments->flag('q')) {
            return Output::QUIET;
        }

        if ($arguments->flag('verbose') || $arguments->flag('v')) {
            return Output::VERBOSE;
        }

        return Output::NORMAL;
    }

    /**
     * @return list<string>
     */
    private function helpLines(): array
    {
        return [
            'apidoc ' . self::VERSION . ' - generate OpenAPI 3.0 documentation from controller docblocks',
            '',
            'Usage:',
            '  apidoc generate [options]   Scan the controllers and write the specification',
            '  apidoc validate [options]   Scan and report problems without writing anything',
            '  apidoc version              Print the version',
            '  apidoc help                 Show this help',
            '',
            'Options:',
            '  -c, --config <file>     Configuration file (default: apidoc.php, .json, .yaml or .yml)',
            '      --no-reflection     Do not use PHP reflection to complete the parameters',
            '      --strict            Treat warnings as errors (exit code 2)',
            '      --no-fail-on-empty  Do not fail when no endpoint was found',
            '      --all               Also document methods that carry no apidoc tag',
            '      --no-infer          Never derive a missing @route from the namespace',
            '  -v, --verbose           Print scanned files and notices',
            '  -q, --quiet             Only print errors',
            '  -h, --help              Show this help',
            '',
            'Exit codes:',
            '  0  success',
            '  1  the run failed (invalid configuration, unwritable output, ...)',
            '  2  the specification was generated but problems were reported',
        ];
    }
}
