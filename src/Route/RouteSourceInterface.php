<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Route;

/**
 * A source of route definitions.
 *
 * Implementations read the route table from somewhere: a framework, a file, or
 * an already parsed document. They must never throw for a route they cannot
 * understand; skipping it and reporting a diagnostic is the expected behaviour.
 */
interface RouteSourceInterface
{
    /**
     * @return list<RouteDefinition>
     */
    public function routes(): array;

    /**
     * Human readable name, used in diagnostics and in --verbose output.
     */
    public function name(): string;
}
