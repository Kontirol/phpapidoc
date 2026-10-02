<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Cli\Command;

use Kontirol\ApiDoc\Application;
use Kontirol\ApiDoc\Cli\Arguments;
use Kontirol\ApiDoc\Model\Diagnostic;
use Kontirol\ApiDoc\Validator\DocumentValidator;

/**
 * Same pipeline as generate, minus the writing, plus extra checks.
 */
final class ValidateCommand extends AbstractCommand
{
    public function execute(Arguments $arguments): int
    {
        $config = $this->loadConfig($arguments);

        $document = (new Application($config, $this->routeSources($config)))->buildDocument();

        /** @var list<Diagnostic> $diagnostics */
        $diagnostics = array_merge(
            $document->diagnostics,
            (new DocumentValidator())->validate($document)
        );

        $documented = count($document->documentedEndpoints());

        $this->output->line(sprintf(
            '%d endpoint(s) documented, %d ignored.',
            $documented,
            count($document->endpoints) - $documented
        ));

        $counts = $this->countDiagnostics($diagnostics);

        $this->printDiagnostics($diagnostics);

        if ($counts['error'] === 0 && $counts['warning'] === 0) {
            $this->output->line();
            $this->output->line('No problems found.');
        }

        return $this->exitCode($counts, $config->isStrict());
    }
}
