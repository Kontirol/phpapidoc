<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Reflector;

use Kontirol\ApiDoc\Model\ApiEndpoint;
use Kontirol\ApiDoc\Model\Diagnostic;
use Kontirol\ApiDoc\Model\Parameter;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * Adds parameters that exist in the PHP method signature but are missing from
 * the docblock.
 *
 * Reflection is strictly a best effort enhancement. A controller that cannot be
 * autoloaded, or one that reads its input through the request object, simply
 * keeps the parameters that were documented. That also means the enricher never
 * complains about a documented parameter that has no matching method argument:
 * in frameworks like ThinkPHP that is the normal case.
 */
final class ReflectionEnricher
{
    /**
     * @var list<Diagnostic>
     */
    private $diagnostics = [];

    public function enrich(ApiEndpoint $endpoint): void
    {
        $method = $this->reflectMethod($endpoint->controller, $endpoint->action);

        if ($method === null) {
            return;
        }

        foreach ($method->getParameters() as $parameter) {
            $this->enrichParameter($endpoint, $parameter);
        }
    }

    /**
     * @return list<Diagnostic>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    private function enrichParameter(ApiEndpoint $endpoint, ReflectionParameter $parameter): void
    {
        $name = $parameter->getName();

        if ($endpoint->hasParameter($name)) {
            return;
        }

        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType) {
            return;
        }

        // Class typed arguments are injected by the container, not bound from
        // the request, so they are not part of the public API.
        if ($type->isBuiltin() === false) {
            return;
        }

        $generated = new Parameter($name, self::mapBuiltinType($type->getName()));
        $generated->required = !$parameter->isOptional();

        if ($parameter->isDefaultValueAvailable()) {
            $generated->hasDefault = true;
            $generated->default = $parameter->getDefaultValue();
        }

        $endpoint->addParameter($generated);

        $this->diagnostics[] = Diagnostic::notice(
            'param.undocumented',
            sprintf(
                'Parameter "$%s" is used by %s::%s() but has no @param tag; it was documented automatically.',
                $name,
                $endpoint->shortController(),
                $endpoint->action
            ),
            $endpoint->file,
            $endpoint->line
        );
    }

    private function reflectMethod(string $class, string $method): ?ReflectionMethod
    {
        try {
            if (!class_exists($class)) {
                return null;
            }

            $reflection = new ReflectionClass($class);

            if (!$reflection->hasMethod($method)) {
                return null;
            }

            return $reflection->getMethod($method);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private static function mapBuiltinType(string $type): string
    {
        switch (strtolower($type)) {
            case 'int':
                return 'integer';
            case 'float':
                return 'number';
            case 'bool':
                return 'boolean';
            case 'array':
                return 'array';
            case 'object':
                return 'object';
            default:
                return 'string';
        }
    }
}
