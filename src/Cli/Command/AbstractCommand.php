<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Cli\Command;

use Kontirol\ApiDoc\Cli\Arguments;
use Kontirol\ApiDoc\Cli\Console;
use Kontirol\ApiDoc\Cli\Output;
use Kontirol\ApiDoc\Config\Config;
use Kontirol\ApiDoc\Config\ConfigLoader;
use Kontirol\ApiDoc\Exception\ConfigException;
use Kontirol\ApiDoc\Model\Diagnostic;
use Kontirol\ApiDoc\Route\RouteSourceInterface;
use Kontirol\ApiDoc\Route\ThinkPhpRouteSource;

/**
 * Shared plumbing for the console commands.
 */
abstract class AbstractCommand
{
    /**
     * @var list<string>
     */
    protected const CONFIG_FILES = ['apidoc.php', 'apidoc.json', 'apidoc.yaml', 'apidoc.yml'];

    /**
     * @var Output
     */
    protected $output;

    public function __construct(Output $output)
    {
        $this->output = $output;
    }

    protected function loadConfig(Arguments $arguments): Config
    {
        $path = $this->locateConfig($arguments);

        $this->output->verbose('Configuration file: ' . $path);

        return ConfigLoader::load($path)->with($this->overrides($arguments));
    }

    /**
     * Command line switches that win over the configuration file.
     *
     * @return array<string, mixed>
     */
    protected function overrides(Arguments $arguments): array
    {
        $overrides = [];

        if ($arguments->flag('no-reflection')) {
            $overrides['reflection'] = false;
        }

        if ($arguments->flag('reflection')) {
            $overrides['reflection'] = true;
        }

        if ($arguments->flag('strict')) {
            $overrides['strict'] = true;
        }

        if ($arguments->flag('no-fail-on-empty')) {
            $overrides['fail_on_empty'] = false;
        }

        if ($arguments->flag('include-undocumented') || $arguments->flag('all')) {
            $overrides['include_undocumented'] = true;
        }

        if ($arguments->flag('no-infer')) {
            $overrides['route'] = ['infer' => false];
        }

        return $overrides;
    }

    /**
     * Builds the route sources the configuration asks for.
     *
     * "auto" (the default) means: use the framework route table when the
     * framework happens to be installed in the current process, and quietly
     * fall back to @route alone when it is not.
     *
     * @return list<RouteSourceInterface>
     */
    protected function routeSources(Config $config): array
    {
        $configured = $config->routeSource();

        if ($configured === Config::ROUTE_SOURCE_ANNOTATION) {
            return [];
        }

        if ($configured === Config::ROUTE_SOURCE_THINKPHP || class_exists('think\\App')) {
            return [new ThinkPhpRouteSource($config->thinkPhpBootstrap())];
        }

        return [];
    }

    protected function locateConfig(Arguments $arguments): string
    {
        $explicit = $arguments->option('config');

        if ($explicit === null) {
            $explicit = $arguments->option('c');
        }

        if ($explicit !== null) {
            return $explicit;
        }

        foreach (self::CONFIG_FILES as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw ConfigException::fileNotFound(
            implode(' or ', self::CONFIG_FILES) . ' in ' . (string) getcwd()
        );
    }

    /**
     * @param list<Diagnostic> $diagnostics
     *
     * @return array{error: int, warning: int, notice: int}
     */
    protected function countDiagnostics(array $diagnostics): array
    {
        $counts = ['error' => 0, 'warning' => 0, 'notice' => 0];

        foreach ($diagnostics as $diagnostic) {
            if (isset($counts[$diagnostic->level])) {
                $counts[$diagnostic->level]++;
            }
        }

        return $counts;
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    protected function printDiagnostics(array $diagnostics): void
    {
        if ($diagnostics === []) {
            return;
        }

        $counts = $this->countDiagnostics($diagnostics);

        $this->output->line();
        $this->output->line(sprintf(
            'Diagnostics: %d error(s), %d warning(s), %d notice(s)',
            $counts['error'],
            $counts['warning'],
            $counts['notice']
        ));

        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic->level === 'notice' && $this->output->verbosity() < Output::VERBOSE) {
                continue;
            }

            $this->output->line('  ' . $this->formatDiagnostic($diagnostic));
        }

        if ($counts['notice'] > 0 && $this->output->verbosity() < Output::VERBOSE) {
            $this->output->line('  (notices are hidden, run with -v to see them)');
        }
    }

    protected function formatDiagnostic(Diagnostic $diagnostic): string
    {
        $location = '';

        if ($diagnostic->file !== null) {
            $location = $diagnostic->file;

            if ($diagnostic->line !== null) {
                $location .= ':' . $diagnostic->line;
            }

            $location .= '  ';
        }

        return sprintf(
            '%s[%s/%s] %s',
            $location,
            $diagnostic->level,
            $diagnostic->code,
            $diagnostic->message
        );
    }

    /**
     * @param array{error: int, warning: int, notice: int} $counts
     */
    protected function exitCode(array $counts, bool $strict): int
    {
        if ($counts['error'] > 0) {
            return Console::DIAGNOSTICS;
        }

        if ($strict && $counts['warning'] > 0) {
            return Console::DIAGNOSTICS;
        }

        return Console::SUCCESS;
    }
}
