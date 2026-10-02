<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Route;

use Kontirol\ApiDoc\Model\ApiEndpoint;
use Kontirol\ApiDoc\Model\Diagnostic;

/**
 * Cross checks the parsed endpoints against a framework route table.
 *
 * The route table is the runtime truth, so a documented @route that disagrees
 * with it is reported and then replaced by the framework value.
 *
 * Routes are matched by controller and action, never by URL. Frameworks hand out
 * controller names relative to the current application, so a route pointing at
 * "Order/detail" has to find "App\Api\Controller\Order::detail()". Every
 * endpoint is therefore indexed under both its fully qualified and its short
 * name, and an ambiguous short name is refused rather than guessed.
 */
final class RouteMatcher
{
    /**
     * Sentinel stored in the index when a short name maps to several endpoints.
     */
    private const AMBIGUOUS = -1;

    /**
     * @var list<Diagnostic>
     */
    private $diagnostics = [];

    /**
     * @param list<ApiEndpoint>     $endpoints
     * @param list<RouteDefinition> $routes
     *
     * @return list<ApiEndpoint>
     */
    public function apply(array $endpoints, array $routes): array
    {
        $index = $this->index($endpoints);

        foreach ($routes as $route) {
            $key = $route->key();

            if ($key === null) {
                // Routes bound to a closure or an unresolved target cannot be
                // matched against a controller method, so they are skipped.
                continue;
            }

            $position = $index[strtolower($key)] ?? null;

            if ($position === self::AMBIGUOUS) {
                $this->diagnostics[] = Diagnostic::warning(
                    'route.ambiguous',
                    sprintf(
                        'Route %s %s -> %s matches more than one controller, it was not applied.',
                        $route->method,
                        $route->path,
                        $key
                    )
                );

                continue;
            }

            if ($position === null) {
                $this->diagnostics[] = Diagnostic::notice(
                    'route.undocumented',
                    sprintf(
                        'Route %s %s -> %s has no @route documentation.',
                        $route->method,
                        $route->path,
                        $key
                    )
                );

                continue;
            }

            $this->merge($endpoints[$position], $route);
        }

        return $endpoints;
    }

    /**
     * @return list<Diagnostic>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * Maps every way an endpoint can be addressed to its position.
     *
     * @param list<ApiEndpoint> $endpoints
     *
     * @return array<string, int>
     */
    private function index(array $endpoints): array
    {
        $index = [];

        foreach ($endpoints as $position => $endpoint) {
            $action = strtolower($endpoint->action);

            $index[strtolower($endpoint->controller) . '::' . $action] = $position;

            foreach ($this->shortNames($endpoint->controller) as $short) {
                $key = $short . '::' . $action;

                if (!isset($index[$key])) {
                    $index[$key] = $position;

                    continue;
                }

                if ($index[$key] !== $position) {
                    $index[$key] = self::AMBIGUOUS;
                }
            }
        }

        return $index;
    }

    /**
     * The controller name the way a framework route would spell it.
     *
     * @return list<string>
     */
    private function shortNames(string $controller): array
    {
        $controller = trim($controller, '\\');

        if ($controller === '') {
            return [];
        }

        $segments = explode('\\', $controller);

        return [strtolower((string) end($segments))];
    }

    private function merge(ApiEndpoint $endpoint, RouteDefinition $route): void
    {
        if ($route->name !== null) {
            $endpoint->extra['routeName'] = $route->name;
        }

        if (!$endpoint->hasRoute()) {
            $endpoint->route = $route->path;
        } elseif ($endpoint->route !== $route->path) {
            $this->diagnostics[] = Diagnostic::warning(
                'route.mismatch',
                sprintf(
                    'Documented route %s does not match the framework route %s, the framework wins.',
                    (string) $endpoint->route,
                    $route->path
                ),
                $endpoint->file,
                $endpoint->line
            );

            $endpoint->route = $route->path;
        }

        if ($route->middleware !== []) {
            $endpoint->extra['middleware'] = $route->middleware;
        }

        if ($route->method === '*') {
            return;
        }

        if ($endpoint->httpMethod === null || $endpoint->httpMethod === '') {
            $endpoint->httpMethod = $route->method;

            return;
        }

        if ($endpoint->httpMethod !== $route->method) {
            $this->diagnostics[] = Diagnostic::warning(
                'route.method_mismatch',
                sprintf(
                    'Documented method %s does not match the framework route method %s, the framework wins.',
                    $endpoint->httpMethod,
                    $route->method
                ),
                $endpoint->file,
                $endpoint->line
            );

            $endpoint->httpMethod = $route->method;
        }
    }
}
