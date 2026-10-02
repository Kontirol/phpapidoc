<?php

/**
 * Dumps the raw ThinkPHP route table.
 *
 * ThinkPhpRouteSource walks the rule list generically instead of relying on a
 * documented shape, so this script exists to check that assumption against a
 * real application and to make the actual structure visible when something
 * does not line up.
 *
 * Usage:
 *
 *     php tools/inspect-thinkphp.php /path/to/thinkphp-project
 *
 * The project is bootstrapped in process. Nothing is written to the project.
 */

declare(strict_types=1);

$root = $argv[1] ?? null;

if ($root === null) {
    fwrite(STDERR, "Usage: php tools/inspect-thinkphp.php <thinkphp-project-root>\n");
    exit(1);
}

$root = rtrim($root, "/\\");

$bootstrap = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($bootstrap)) {
    fwrite(STDERR, 'Cannot find ' . $bootstrap . PHP_EOL);

    exit(1);
}

// Load apidoc's own autoloader first, so that ThinkPhpRouteSource is available
// while the host application supplies think\App.
foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../autoload.php'] as $candidate) {
    if (is_file($candidate)) {
        require_once $candidate;

        break;
    }
}

require $bootstrap;

if (!class_exists('think\\App')) {
    fwrite(STDERR, 'think\\App is not available: is this really a ThinkPHP project?' . PHP_EOL);

    exit(1);
}

echo 'ThinkPHP version: ' . (defined('think\\App::VERSION') ? constant('think\\App::VERSION') : 'unknown') . PHP_EOL;
echo 'Project root: ' . $root . PHP_EOL . PHP_EOL;

try {
    $application = new think\App($root);
    $application->initialize();
} catch (Throwable $exception) {
    fwrite(STDERR, 'Bootstrap failed: ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);

    exit(1);
}

$route = $application->__get('route');

if (!is_object($route)) {
    fwrite(STDERR, 'think\App::__get("route") did not return an object.' . PHP_EOL);

    exit(1);
}

echo 'Route manager: ' . get_class($route) . PHP_EOL;

if (!method_exists($route, 'getRuleList')) {
    fwrite(STDERR, 'No getRuleList() method on the route manager. Available methods:' . PHP_EOL);

    foreach (get_class_methods($route) as $method) {
        echo '  ' . $method . PHP_EOL;
    }

    exit(1);
}

$rules = $route->getRuleList();

echo 'getRuleList() returned ' . gettype($rules) . PHP_EOL . PHP_EOL;

echo '=== structure (depth <= 6) ===' . PHP_EOL . PHP_EOL;

dumpNode('root', $rules, 0, 6);

echo PHP_EOL . '=== as parsed by ThinkPhpRouteSource ===' . PHP_EOL . PHP_EOL;

$source = new Kontirol\ApiDoc\Route\ThinkPhpRouteSource(null, static function () use ($rules) {
    return is_array($rules) ? $rules : [];
});

$definitions = $source->routes();

printf('%-6s %-40s %s%s', 'METHOD', 'PATH', 'TARGET', PHP_EOL);

foreach ($definitions as $definition) {
    printf(
        '%-6s %-40s %s%s',
        $definition->method,
        $definition->path,
        $definition->key() === null ? '(unresolved)' : $definition->key(),
        PHP_EOL
    );
}

echo PHP_EOL . count($definitions) . ' route(s) parsed.' . PHP_EOL;

/**
 * @param mixed $value
 */
function dumpNode(string $path, $value, int $depth, int $maxDepth): void
{
    if ($depth > $maxDepth) {
        return;
    }

    $indent = str_repeat('  ', $depth);

    if (is_array($value)) {
        echo $indent . $path . ' (array, ' . count($value) . ')' . PHP_EOL;

        foreach ($value as $key => $item) {
            dumpNode((string) $key, $item, $depth + 1, $maxDepth);
        }

        return;
    }

    if ($value instanceof Traversable) {
        echo $indent . $path . ' (' . get_class($value) . ')' . PHP_EOL;

        foreach ($value as $key => $item) {
            dumpNode((string) $key, $item, $depth + 1, $maxDepth);
        }

        return;
    }

    if (is_object($value)) {
        echo $indent . $path . ' -> ' . get_class($value) . PHP_EOL;

        foreach (['getRule', 'getRoute', 'getMethod', 'getName', 'getMiddleware'] as $method) {
            if (!method_exists($value, $method)) {
                continue;
            }

            try {
                $result = $value->$method();
            } catch (Throwable $exception) {
                echo $indent . '    ' . $method . '() threw ' . $exception->getMessage() . PHP_EOL;

                continue;
            }

            echo $indent . '    ' . $method . '() = ' . describe($result) . PHP_EOL;
        }

        return;
    }

    echo $indent . $path . ' = ' . describe($value) . PHP_EOL;
}

/**
 * @param mixed $value
 */
function describe($value): string
{
    if (is_scalar($value) || $value === null) {
        return var_export($value, true);
    }

    if (is_array($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return 'array(' . count($value) . ') ' . ($encoded === false ? '[]' : $encoded);
    }

    if ($value instanceof Closure) {
        return 'Closure';
    }

    if (is_object($value)) {
        return get_class($value);
    }

    return gettype($value);
}
