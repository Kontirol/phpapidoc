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
 * Two details of the convention are configurable, because both depend on the
 * application rather than on the framework version.
 *
 * 1. The "Controller" suffix. ThinkPHP's route.controller_suffix defaults to
 *    false, which means the suffix is part of the URL: the class
 *    "app\api\controller\OrderController" answers at "/api/ordercontroller".
 *    With route.controller_suffix = true the same class answers at "/api/order",
 *    because the framework appends the suffix itself when resolving.
 *
 * 2. The casing of a segment. ThinkPHP lowercases the URL and does not split
 *    camel case, so "orderDetail" stays one word: "orderdetail". Projects that
 *    write their own routes are free to use other conventions, hence the CASE_*
 *    alternatives.
 *
 * A namespace that does not look like ThinkPHP yields null, so other frameworks
 * and plain PHP projects are never given an invented URL.
 */
final class UrlInferrer
{
    /**
     * Upper and lower case only: "orderDetail" -> "orderdetail".
     *
     * This is what ThinkPHP does, and therefore the default.
     */
    public const CASE_LOWER = 'lower';

    /**
     * Underscore separated: "orderDetail" -> "order_detail".
     */
    public const CASE_SNAKE = 'snake';

    /**
     * Hyphen separated: "orderDetail" -> "order-detail".
     */
    public const CASE_KEBAB = 'kebab';

    /**
     * Left as written: "orderDetail" -> "orderDetail".
     */
    public const CASE_KEEP = 'keep';

    /**
     * @var list<string>
     */
    private const CASES = [self::CASE_LOWER, self::CASE_SNAKE, self::CASE_KEBAB, self::CASE_KEEP];

    /**
     * @var string
     */
    private $namespacePrefix;

    /**
     * @var string
     */
    private $urlPrefix;

    /**
     * @var bool
     */
    private $controllerSuffix;

    /**
     * @var string
     */
    private $segmentCase;

    /**
     * @param string $namespacePrefix  The namespace the controllers live in.
     * @param string $urlPrefix        Overrides the namespace derived prefix.
     * @param bool   $controllerSuffix Whether "Controller" is stripped from the
     *                                 URL. False matches ThinkPHP's default.
     * @param string $segmentCase      One of the CASE_* constants.
     */
    public function __construct(
        string $namespacePrefix = '',
        string $urlPrefix = '',
        bool $controllerSuffix = false,
        string $segmentCase = self::CASE_LOWER
    ) {
        $this->namespacePrefix = trim($namespacePrefix, '\\');
        $this->urlPrefix = rtrim(trim($urlPrefix), '/');
        $this->controllerSuffix = $controllerSuffix;
        $this->segmentCase = self::normaliseCase($segmentCase);
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
            $path .= '/' . $this->toSegment($action);
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

    /**
     * Whether "Controller" is stripped from the controller segment.
     */
    public function stripsControllerSuffix(): bool
    {
        return $this->controllerSuffix;
    }

    /**
     * The casing applied to every segment.
     */
    public function segmentCase(): string
    {
        return $this->segmentCase;
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
            if ($this->controllerSuffix) {
                $segment = self::stripSuffix($segment);
            }

            if ($segment !== '') {
                $segments[] = $this->toSegment($segment);
            }
        }

        return implode('/', $segments);
    }

    private function toSegment(string $name): string
    {
        switch ($this->segmentCase) {
            case self::CASE_SNAKE:
                return self::snake($name);

            case self::CASE_KEBAB:
                return str_replace('_', '-', self::snake($name));

            case self::CASE_KEEP:
                return $name;

            case self::CASE_LOWER:
            default:
                return strtolower($name);
        }
    }

    private static function normaliseCase(string $case): string
    {
        $case = strtolower(trim($case));

        return in_array($case, self::CASES, true) ? $case : self::CASE_LOWER;
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
