<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Model;

/**
 * The whole parsed API description, framework agnostic.
 *
 * The OpenAPI builder turns this into a specification document.
 */
final class ApiDocument
{
    /**
     * @var array{title: string, version: string, description: string}
     */
    public $info;

    /**
     * @var list<string>
     */
    public $servers = [];

    /**
     * @var list<ApiEndpoint>
     */
    public $endpoints = [];

    /**
     * Tag name => description.
     *
     * @var array<string, string>
     */
    public $tags = [];

    /**
     * Scheme name => scheme.
     *
     * @var array<string, SecurityScheme>
     */
    public $securitySchemes = [];

    /**
     * Requirement name => scopes.
     *
     * @var array<string, list<string>>
     */
    public $defaultSecurity = [];

    /**
     * @var list<Diagnostic>
     */
    public $diagnostics = [];

    /**
     * @param array{title?: string, version?: string, description?: string} $info
     * @param list<string>                                                 $servers
     */
    public function __construct(array $info = [], array $servers = [])
    {
        $this->info = [
            'title' => (string) ($info['title'] ?? 'API Documentation'),
            'version' => (string) ($info['version'] ?? '1.0.0'),
            'description' => (string) ($info['description'] ?? ''),
        ];

        $this->servers = $servers;
    }

    public function addEndpoint(ApiEndpoint $endpoint): void
    {
        $this->endpoints[] = $endpoint;
    }

    public function addDiagnostic(Diagnostic $diagnostic): void
    {
        $this->diagnostics[] = $diagnostic;
    }

    /**
     * @param iterable<Diagnostic> $diagnostics
     */
    public function addDiagnostics(iterable $diagnostics): void
    {
        foreach ($diagnostics as $diagnostic) {
            $this->addDiagnostic($diagnostic);
        }
    }

    public function getEndpoint(string $id): ?ApiEndpoint
    {
        foreach ($this->endpoints as $endpoint) {
            if ($endpoint->id() === $id) {
                return $endpoint;
            }
        }

        return null;
    }

    /**
     * Endpoints that will actually be written out.
     *
     * @return list<ApiEndpoint>
     */
    public function documentedEndpoints(): array
    {
        $result = [];

        foreach ($this->endpoints as $endpoint) {
            if (!$endpoint->ignored) {
                $result[] = $endpoint;
            }
        }

        return $result;
    }

    /**
     * @return list<Diagnostic>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->diagnostics, static function (Diagnostic $diagnostic): bool {
            return $diagnostic->isError();
        }));
    }

    /**
     * @return list<Diagnostic>
     */
    public function warnings(): array
    {
        return array_values(array_filter($this->diagnostics, static function (Diagnostic $diagnostic): bool {
            return $diagnostic->isWarning();
        }));
    }

    public function hasErrors(): bool
    {
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->isError()) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return $this->documentedEndpoints() === [];
    }

    public function count(): int
    {
        return count($this->endpoints);
    }
}
