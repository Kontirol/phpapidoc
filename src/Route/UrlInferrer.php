<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Route;

/**
 * Builds the URL of a controller action the way ThinkPHP's pathinfo router
 * would resolve it.
 *
 *     namespace app\api\controller;
 *     class Translate            ->  /api/translate/text
 *     { public function text() {} }
 *
 * Only the ThinkPHP conventions are recognised. The URL prefix comes from the
 * configuration first, and otherwise from the namespace:
 *
 *     app\api\controller   ->  /api      (multi application)
 *     app\controller       ->  (root)    (single application)
 *
 * A namespace that does not look like ThinkPHP yields null, so other frameworks
 * and plain PHP projects are never given an invented URL.
 */
final class UrlInferrer
{
    /**
     * @var string
     */
    private $namespacePrefix;

    /**
     * @var string
     */
    private $urlPrefix;

    public function __construct(string $namespacePrefix = '', string $urlPrefix = '')
    {
        $this->namespacePrefix = trim($namespacePrefix, '\\');
        $this->urlPrefix = rtrim(trim($urlPrefix), '/');
    }

    /**
     * Returns the inferred path, or null when no convention applies.
     */
    public function infer(string $controller, string $action): ?string
    {
        $prefix = $this->resolvePrefix();

        if ($prefix === null) {
            return null;
        }

        $path = $this->controllerPath($controller);

        if ($path === '') {
            return null;
        }

        if ($action !== '') {
            $path .= '/' . self::snake($action);
        }

        return $prefix . '/' . $path;
    }

    /**
     * Whether this inferrer knows enough to produce URLs at all.
     */
    public function isUsable(): bool
    {
        return $this->resolvePrefix() !== null;
    }

    private function resolvePrefix(): ?string
    {
        if ($this->urlPrefix !== '') {
            return $this->urlPrefix;
        }

        return self::prefixFromNamespace($this->namespacePrefix);
    }

    /**
     * The controller part of the path, relative to the configured namespace.
     */
    private function controllerPath(string $controller): string
    {
        $controller = trim($controller, '\\');

        if ($this->namespacePrefix !== '' && strpos($controller, $this->namespacePrefix . '\\') === 0) {
            $controller = substr($controller, strlen($this->namespacePrefix) + 1);
        }

        $segments = [];

        foreach (explode('\\', $controller) as $segment) {
            $segment = self::stripSuffix($segment);

            if ($segment !== '') {
                $segments[] = self::snake($segment);
            }
        }

        return implode('/', $segments);
    }

    private static function stripSuffix(string $segment): string
    {
        if (strlen($segment) > 10 && substr($segment, -10) === 'Controller') {
            return substr($segment, 0, -10);
        }

        return $segment;
    }

    private static function prefixFromNamespace(string $namespace): ?string
    {
        if ($namespace === '') {
            return null;
        }

        // app\api\controller -> /api
        if (preg_match('#^app\\\\([^\\\\]+)\\\\controller$#i', $namespace, $matches) === 1) {
            return '/' . strtolower($matches[1]);
        }

        // app\controller -> root
        if (preg_match('#^app\\\\controller$#i', $namespace) === 1) {
            return '';
        }

        return null;
    }

    /**
     * UserOrder -> user_order, getUserInfo -> get_user_info.
     */
    private static function snake(string $name): string
    {
        $name = (string) preg_replace('#(?<!^)[A-Z]#', '_$0', $name);

        return strtolower($name);
    }
}
