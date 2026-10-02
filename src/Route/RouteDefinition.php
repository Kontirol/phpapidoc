<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Route;

/**
 * One entry coming from a framework route table.
 */
final class RouteDefinition
{
    /**
     * @var string Upper case HTTP verb, "*" when the route accepts any method.
     */
    public $method;

    /**
     * @var string Normalised path, path variables written as {name}.
     */
    public $path;

    /**
     * @var string|null Fully qualified controller class.
     */
    public $controller;

    /**
     * @var string|null Method name.
     */
    public $action;

    /**
     * @var string|null Route name, when the framework knows one.
     */
    public $name;

    /**
     * @var list<string>
     */
    public $middleware = [];

    public function __construct(string $method, string $path, ?string $controller = null, ?string $action = null)
    {
        $method = strtoupper(trim($method));

        $this->method = $method === '' ? '*' : $method;
        $this->path = self::normalizePath($path);
        $this->controller = $controller;
        $this->action = $action;
    }

    public function isResolved(): bool
    {
        return $this->controller !== null && $this->controller !== ''
            && $this->action !== null && $this->action !== '';
    }

    public function key(): ?string
    {
        return $this->isResolved() ? $this->controller . '::' . $this->action : null;
    }

    /**
     * Converts framework placeholders into the OpenAPI style.
     *
     *     order/detail   -> /order/detail
     *     order/:id      -> /order/{id}
     *     order/<id>     -> /order/{id}
     */
    public static function normalizePath(string $path): string
    {
        $path = trim($path);

        // Drop a query string or fragment, they are not part of the route.
        $cut = strcspn($path, '?#');
        $path = substr($path, 0, $cut);

        $path = (string) preg_replace('#<([A-Za-z_][A-Za-z0-9_]*)>#', '{$1}', $path);
        $path = (string) preg_replace('#:([A-Za-z_][A-Za-z0-9_]*)#', '{$1}', $path);

        if ($path === '') {
            return '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }
}
