<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Route;

use Kontirol\ApiDoc\Exception\RouteException;
use Throwable;
use Traversable;

/**
 * Reads the route table of a ThinkPHP application.
 *
 * ThinkPHP hands out its rules in an undocumented, version dependent shape, so
 * nothing about nesting or types is assumed here. Two leaf shapes are
 * recognised:
 *
 *   - a plain array such as think\Route::getRuleList() returns on ThinkPHP 8
 *     (["method" => "get", "rule" => "user/list", "route" => "User/index", ...])
 *   - an object exposing getRule(), as older releases and rule groups do
 *
 * Everything else is walked recursively. Passing a provider skips the
 * bootstrapping step and feeds a raw rule list in, which is how the tests
 * exercise the normalisation.
 *
 * The bootstrapped application is also asked how it spells URLs (see
 * FrameworkConventionsInterface), because a controller class name alone does not
 * tell whether it is reached at "/api/order" or "/api/ordercontroller".
 */
final class ThinkPhpRouteSource implements RouteSourceInterface, FrameworkConventionsInterface
{
    /**
     * Guard against a pathological rule tree.
     */
    private const MAX_DEPTH = 8;

    /**
     * Routes ThinkPHP registers for itself; they are not part of the API.
     */
    private const FRAMEWORK_RULE_PATTERN = '#<(__)?miss(__)?>#i';

    /**
     * @var string|null
     */
    private $bootstrap;

    /**
     * @var callable|null
     */
    private $provider;

    /**
     * Raw rule list, cached so the application is only bootstrapped once.
     *
     * @var array<mixed>|null
     */
    private $raw;

    /**
     * @var array{controllerSuffix: ?bool, urlCase: ?string}
     */
    private $conventions = ['controllerSuffix' => null, 'urlCase' => null];

    /**
     * @param string|null   $bootstrap Path to the application's vendor/autoload.php.
     * @param callable|null $provider  Returns the raw rule list.
     */
    public function __construct(?string $bootstrap = null, ?callable $provider = null)
    {
        $this->bootstrap = $bootstrap;
        $this->provider = $provider;
    }

    public function name(): string
    {
        return 'thinkphp';
    }

    /**
     * @return list<RouteDefinition>
     */
    public function routes(): array
    {
        /** @var list<RouteDefinition> $definitions */
        $definitions = [];

        $this->collect($this->rawRules(), $definitions, 0);

        return $definitions;
    }

    public function urlConventions(): array
    {
        $this->rawRules();

        return $this->conventions;
    }

    /**
     * The rule list, bootstrapping the application on first use.
     *
     * @return array<mixed>
     */
    private function rawRules(): array
    {
        if ($this->raw !== null) {
            return $this->raw;
        }

        if ($this->provider !== null) {
            $rules = call_user_func($this->provider);

            return $this->raw = is_array($rules) ? $rules : [];
        }

        return $this->raw = $this->loadFromApplication();
    }

    /**
     * @return array<mixed>
     */
    private function loadFromApplication(): array
    {
        if ($this->bootstrap !== null) {
            if (!is_file($this->bootstrap)) {
                throw RouteException::bootstrapFailed($this->bootstrap, 'the bootstrap file does not exist');
            }

            require_once $this->bootstrap;
        }

        if (!class_exists('think\\App')) {
            throw RouteException::frameworkMissing('think\\App');
        }

        try {
            $application = new \think\App();
            $application->initialize();
        } catch (Throwable $exception) {
            throw RouteException::bootstrapFailed(
                (string) $this->bootstrap,
                $exception->getMessage()
            );
        }

        // Read the conventions before touching the route manager: knowing how
        // URLs are spelled is useful even when the rule list cannot be read.
        $this->conventions = self::readConventions($application);

        $route = $this->routeManager($application);

        $rules = call_user_func([$route, 'getRuleList']);

        return is_array($rules) ? $rules : [];
    }

    /**
     * think\App resolves "route" lazily through the container, so it is reached
     * via __get() rather than a declared property.
     *
     * @param object $application
     *
     * @return object
     */
    private function routeManager(object $application)
    {
        $route = null;

        if (method_exists($application, '__get')) {
            $route = $application->__get('route');
        }

        if (!is_object($route) || !method_exists($route, 'getRuleList')) {
            throw RouteException::frameworkMissing('think\\Route');
        }

        return $route;
    }

    /**
     * Asks the bootstrapped application how it spells URLs.
     *
     * Both lookups are best effort: a configuration key that is missing, or a
     * container that refuses to answer, simply yields null and the caller falls
     * back to its own settings.
     *
     * @param object $application
     *
     * @return array{controllerSuffix: ?bool, urlCase: ?string}
     */
    private static function readConventions(object $application): array
    {
        $config = null;

        try {
            if (method_exists($application, '__get')) {
                $config = $application->__get('config');
            }
        } catch (Throwable $exception) {
            $config = null;
        }

        if (!is_object($config) || !method_exists($config, 'get')) {
            return ['controllerSuffix' => null, 'urlCase' => null];
        }

        return [
            'controllerSuffix' => self::readBool($config, 'route.controller_suffix'),
            'urlCase' => self::readUrlCase($config),
        ];
    }

    /**
     * @param object $config
     */
    private static function readBool(object $config, string $key): ?bool
    {
        try {
            $value = $config->get($key, null);
        } catch (Throwable $exception) {
            return null;
        }

        return is_bool($value) ? $value : null;
    }

    /**
     * app.url_convert decides whether the router lowercases the request path.
     * ThinkPHP enables it by default, so a missing key stays unknown rather than
     * being guessed at.
     *
     * @param object $config
     */
    private static function readUrlCase(object $config): ?string
    {
        $convert = self::readBool($config, 'app.url_convert');

        if ($convert === null) {
            return null;
        }

        return $convert ? UrlInferrer::CASE_LOWER : UrlInferrer::CASE_KEEP;
    }

    /**
     * @param mixed                 $value
     * @param list<RouteDefinition> $definitions
     */
    private function collect($value, array &$definitions, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }

        if (is_array($value)) {
            if (self::isRuleArray($value)) {
                $definition = self::fromRuleArray($value);

                if ($definition !== null) {
                    $definitions[] = $definition;
                }

                return;
            }

            foreach ($value as $item) {
                $this->collect($item, $definitions, $depth + 1);
            }

            return;
        }

        if (is_object($value)) {
            if (method_exists($value, 'getRule')) {
                $definition = self::fromRuleObject($value);

                if ($definition !== null) {
                    $definitions[] = $definition;
                }

                return;
            }

            if ($value instanceof Traversable) {
                foreach ($value as $item) {
                    $this->collect($item, $definitions, $depth + 1);
                }
            }
        }
    }

    /**
     * The entry shape used by think\Route::getRuleList() on ThinkPHP 6 and 8:
     *
     *     ['method' => 'get', 'rule' => 'user/list', 'route' => 'User/index',
     *      'name' => 'user.list', 'domain' => '-', 'pattern' => [],
     *      'option' => ['middleware' => [...], ...]]
     *
     * @param array<mixed> $value
     */
    private static function isRuleArray(array $value): bool
    {
        return array_key_exists('rule', $value) && array_key_exists('method', $value);
    }

    /**
     * @param array<mixed> $item
     */
    private static function fromRuleArray(array $item): ?RouteDefinition
    {
        $rule = $item['rule'] ?? null;

        if (!is_string($rule) || trim($rule) === '') {
            return null;
        }

        if (preg_match(self::FRAMEWORK_RULE_PATTERN, $rule) === 1) {
            return null;
        }

        $method = $item['method'] ?? '*';

        [$controller, $action] = self::resolveTarget($item['route'] ?? null);

        $definition = new RouteDefinition(
            is_string($method) ? $method : '*',
            $rule,
            $controller,
            $action
        );

        $name = $item['name'] ?? null;

        if (is_string($name) && $name !== '') {
            $definition->name = $name;
        }

        $option = $item['option'] ?? null;

        if (is_array($option)) {
            $definition->middleware = self::middlewareNames($option['middleware'] ?? null);
        }

        return $definition;
    }

    private static function fromRuleObject(object $item): ?RouteDefinition
    {
        $rule = call_user_func([$item, 'getRule']);

        if (!is_string($rule) || trim($rule) === '') {
            return null;
        }

        if (preg_match(self::FRAMEWORK_RULE_PATTERN, $rule) === 1) {
            return null;
        }

        $method = method_exists($item, 'getMethod') ? call_user_func([$item, 'getMethod']) : '*';

        $target = method_exists($item, 'getRoute') ? call_user_func([$item, 'getRoute']) : null;

        [$controller, $action] = self::resolveTarget($target);

        $definition = new RouteDefinition(
            is_string($method) ? $method : '*',
            $rule,
            $controller,
            $action
        );

        if (method_exists($item, 'getName')) {
            $name = call_user_func([$item, 'getName']);

            if (is_string($name) && $name !== '') {
                $definition->name = $name;
            }
        }

        if (method_exists($item, 'getMiddleware')) {
            $definition->middleware = self::middlewareNames(call_user_func([$item, 'getMiddleware']));
        }

        return $definition;
    }

    /**
     * @param mixed $target
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function resolveTarget($target): array
    {
        if (is_string($target) && trim($target) !== '') {
            return self::splitTarget($target);
        }

        if (is_array($target) && count($target) >= 2) {
            $class = $target[0];
            $action = $target[1];

            if (is_string($class) && is_string($action)) {
                return [ltrim($class, '\\'), $action];
            }
        }

        return [null, null];
    }

    /**
     * "app\api\controller\Order@detail" -> ["app\api\controller\Order", "detail"]
     * "Order/detail"                    -> ["Order", "detail"]
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function splitTarget(string $target): array
    {
        $target = ltrim(trim($target), '\\');

        $at = strpos($target, '@');

        if ($at !== false) {
            $class = substr($target, 0, $at);
            $action = substr($target, $at + 1);

            return [$class === '' ? null : $class, $action === '' ? null : $action];
        }

        $slash = strrpos($target, '/');

        if ($slash === false) {
            return [null, null];
        }

        $class = substr($target, 0, $slash);
        $action = substr($target, $slash + 1);

        return [$class === '' ? null : $class, $action === '' ? null : $action];
    }

    /**
     * @param mixed $middleware
     *
     * @return list<string>
     */
    private static function middlewareNames($middleware): array
    {
        if (is_string($middleware)) {
            return $middleware === '' ? [] : [$middleware];
        }

        if (!is_array($middleware)) {
            return [];
        }

        $names = [];

        foreach ($middleware as $entry) {
            if (is_string($entry) && $entry !== '') {
                $names[] = $entry;

                continue;
            }

            if (is_object($entry)) {
                $names[] = get_class($entry);
            }
        }

        return $names;
    }
}
