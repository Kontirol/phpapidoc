<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc;

use Kontirol\ApiDoc\Config\Config;
use Kontirol\ApiDoc\Exception\OutputException;
use Kontirol\ApiDoc\Model\ApiDocument;
use Kontirol\ApiDoc\Model\ApiEndpoint;
use Kontirol\ApiDoc\Model\Diagnostic;
use Kontirol\ApiDoc\Model\SecurityScheme;
use Kontirol\ApiDoc\OpenApi\OpenApiBuilder;
use Kontirol\ApiDoc\Output\DocumentWriter;
use Kontirol\ApiDoc\Parser\EndpointBuilder;
use Kontirol\ApiDoc\Parser\SourceScanner;
use Kontirol\ApiDoc\Reflector\ReflectionEnricher;
use Kontirol\ApiDoc\Route\RouteDefinition;
use Kontirol\ApiDoc\Route\RouteMatcher;
use Kontirol\ApiDoc\Route\RouteSourceInterface;
use Kontirol\ApiDoc\Route\UrlInferrer;
use Kontirol\ApiDoc\Scanner\ControllerScanner;

/**
 * Wires the whole pipeline together: scan, parse, enrich, render, write.
 */
final class Application
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var list<RouteSourceInterface>
     */
    private $routeSources;

    /**
     * @param list<RouteSourceInterface> $routeSources
     */
    public function __construct(Config $config, array $routeSources = [])
    {
        $this->config = $config;
        $this->routeSources = $routeSources;
    }

    public function run(): GenerationResult
    {
        $document = $this->buildDocument();

        if ($this->config->failOnEmpty() && $document->documentedEndpoints() === []) {
            throw OutputException::emptyDocument();
        }

        $spec = (new OpenApiBuilder())->build($document);

        $written = (new DocumentWriter())->write($spec, $this->config->outputs());

        return new GenerationResult($document, $spec, $written);
    }

    /**
     * Everything up to, but not including, rendering.
     */
    public function buildDocument(): ApiDocument
    {
        $document = new ApiDocument($this->config->info(), $this->config->servers());
        $document->tags = $this->config->tagDescriptions();
        $document->securitySchemes = $this->securitySchemes();
        $document->defaultSecurity = $this->config->defaultSecurity();

        $builder = new EndpointBuilder(null, null, null, $this->config->includeUndocumented());
        $enricher = $this->config->isReflectionEnabled() ? new ReflectionEnricher() : null;
        $sourceScanner = new SourceScanner();
        $scanner = new ControllerScanner($this->config->excludePatterns());

        foreach ($this->config->controllers() as $source) {
            $inferrer = $this->urlInferrer($source);

            foreach ($scanner->scan([$source]) as $file) {
                $this->collectFile($file->path, $document, $builder, $enricher, $sourceScanner, $inferrer);
            }
        }

        $document->addDiagnostics($builder->diagnostics());

        if ($enricher !== null) {
            $document->addDiagnostics($enricher->diagnostics());
        }

        $this->applyRoutes($document);

        return $document;
    }

    private function collectFile(
        string $path,
        ApiDocument $document,
        EndpointBuilder $builder,
        ?ReflectionEnricher $enricher,
        SourceScanner $sourceScanner,
        ?UrlInferrer $inferrer
    ): void {
        $code = @file_get_contents($path);

        if ($code === false) {
            $document->addDiagnostic(Diagnostic::error(
                'file.unreadable',
                'Unable to read the controller file.',
                $path
            ));

            return;
        }

        foreach ($sourceScanner->scan($code) as $class) {
            foreach ($class->publicMethods() as $method) {
                $endpoint = $builder->build($class->fqcn(), $method, $path);

                if ($endpoint === null) {
                    continue;
                }

                if ($enricher !== null && !$endpoint->ignored) {
                    $enricher->enrich($endpoint);
                }

                if ($this->applyInferredRoute($endpoint, $inferrer, $document)) {
                    $builder->detectPathParameters($endpoint);
                }

                $document->addEndpoint($endpoint);
            }
        }
    }

    /**
     * Fills in a @route that was not written down, when the controller namespace
     * follows a convention apidoc understands.
     */
    private function applyInferredRoute(ApiEndpoint $endpoint, ?UrlInferrer $inferrer, ApiDocument $document): bool
    {
        if ($inferrer === null || $endpoint->ignored || $endpoint->hasRoute()) {
            return false;
        }

        $route = $inferrer->infer($endpoint->controller, $endpoint->action);

        if ($route === null) {
            return false;
        }

        $endpoint->route = $route;
        $endpoint->extra['routeInferred'] = true;

        $document->addDiagnostic(Diagnostic::notice(
            'route.inferred',
            sprintf(
                '%s::%s() has no @route, %s was derived from its namespace.',
                $endpoint->shortController(),
                $endpoint->action,
                $route
            ),
            $endpoint->file,
            $endpoint->line
        ));

        return true;
    }

    /**
     * @param array{path: string, namespace: ?string, prefix: string, exclude: list<string>, suffix: string} $source
     */
    private function urlInferrer(array $source): ?UrlInferrer
    {
        if (!$this->config->inferRoutes()) {
            return null;
        }

        $inferrer = new UrlInferrer(
            is_string($source['namespace'] ?? null) ? $source['namespace'] : '',
            is_string($source['prefix'] ?? null) ? $source['prefix'] : ''
        );

        return $inferrer->isUsable() ? $inferrer : null;
    }

    /**
     * @return array<string, SecurityScheme>
     */
    private function securitySchemes(): array
    {
        $schemes = [];

        foreach ($this->config->securitySchemes() as $name => $definition) {
            $schemes[$name] = SecurityScheme::fromArray($name, $definition);
        }

        return $schemes;
    }

    /**
     * Cross checks the endpoints against the configured route tables.
     *
     * RouteMatcher mutates the ApiEndpoint objects in place, and those are the
     * very same instances the document holds.
     */
    private function applyRoutes(ApiDocument $document): void
    {
        if ($this->routeSources === [] || $document->isEmpty()) {
            return;
        }

        /** @var list<RouteDefinition> $routes */
        $routes = [];

        foreach ($this->routeSources as $source) {
            foreach ($source->routes() as $route) {
                $routes[] = $route;
            }
        }

        if ($routes === []) {
            return;
        }

        $matcher = new RouteMatcher();

        $matcher->apply($document->documentedEndpoints(), $routes);

        $document->addDiagnostics($matcher->diagnostics());
    }
}
