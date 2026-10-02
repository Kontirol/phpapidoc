<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc;

use Kontirol\ApiDoc\Model\ApiDocument;
use Kontirol\ApiDoc\Model\Diagnostic;

/**
 * The outcome of one generation run.
 */
final class GenerationResult
{
    /**
     * @var ApiDocument
     */
    public $document;

    /**
     * @var array<string, mixed>
     */
    public $spec;

    /**
     * @var list<string>
     */
    public $written;

    /**
     * @param array<string, mixed> $spec
     * @param list<string>         $written
     */
    public function __construct(ApiDocument $document, array $spec, array $written)
    {
        $this->document = $document;
        $this->spec = $spec;
        $this->written = $written;
    }

    /**
     * @return list<Diagnostic>
     */
    public function diagnostics(): array
    {
        return $this->document->diagnostics;
    }

    /**
     * Number of endpoints that ended up in the document.
     */
    public function endpointCount(): int
    {
        return count($this->document->documentedEndpoints());
    }

    public function isEmpty(): bool
    {
        return $this->document->isEmpty();
    }
}
