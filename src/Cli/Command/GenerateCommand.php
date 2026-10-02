<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Cli\Command;

use Kontirol\ApiDoc\Application;
use Kontirol\ApiDoc\Cli\Arguments;
use Kontirol\ApiDoc\GenerationResult;
use Kontirol\ApiDoc\Model\Diagnostic;
use Kontirol\ApiDoc\Validator\DocumentValidator;

final class GenerateCommand extends AbstractCommand
{
    public function execute(Arguments $arguments): int
    {
        $config = $this->loadConfig($arguments);

        foreach ($config->controllers() as $source) {
            $this->output->verbose('Scanning ' . $source['path']);
        }

        foreach ($config->outputs() as $output) {
            $this->output->verbose('Output ' . $output['format'] . ': ' . $output['path']);
        }

        $started = microtime(true);

        $result = (new Application($config, $this->routeSources($config)))->run();

        $elapsed = microtime(true) - $started;

        $diagnostics = $this->collectDiagnostics($result);

        $this->output->line(sprintf(
            '%d endpoint(s) documented, %d ignored, in %.0f ms.',
            $result->endpointCount(),
            count($result->document->endpoints) - $result->endpointCount(),
            $elapsed * 1000
        ));

        $this->output->line('Written:');

        foreach ($result->written as $file) {
            $this->output->line(sprintf('  %s  (%s)', $file, $this->humanSize($file)));
        }

        $this->printDiagnostics($diagnostics);

        return $this->exitCode($this->countDiagnostics($diagnostics), $config->isStrict());
    }

    /**
     * @return list<Diagnostic>
     */
    private function collectDiagnostics(GenerationResult $result): array
    {
        return array_merge(
            $result->diagnostics(),
            (new DocumentValidator())->validate($result->document)
        );
    }

    private function humanSize(string $file): string
    {
        $size = @filesize($file);

        if ($size === false) {
            return 'unknown size';
        }

        if ($size < 1024) {
            return $size . ' B';
        }

        return round($size / 1024, 1) . ' KB';
    }
}
