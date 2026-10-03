<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Config;

use Kontirol\ApiDoc\Exception\ConfigException;
use Kontirol\ApiDoc\Support\Arr;
use Kontirol\ApiDoc\Support\Path;

/**
 * Immutable, normalised view over the raw configuration array.
 *
 * Everything the rest of the package needs is exposed through typed getters,
 * so no other class has to know how the config file is shaped.
 */
final class Config
{
    public const ROUTE_SOURCE_AUTO = 'auto';
    public const ROUTE_SOURCE_ANNOTATION = 'annotation';
    public const ROUTE_SOURCE_THINKPHP = 'thinkphp';

    public const FORMAT_JSON = 'json';
    public const FORMAT_YAML = 'yaml';

    /**
     * Ask the loaded framework route source which convention applies.
     */
    public const URL_CONVENTION_AUTO = 'auto';

    /**
     * Keep only the case of a segment: "orderDetail" -> "orderdetail".
     *
     * This is what ThinkPHP does and therefore the default.
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
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'controllers' => [],
        'output' => [
            self::FORMAT_JSON => 'openapi.json',
            self::FORMAT_YAML => null,
        ],
        'info' => [
            'title' => 'API Documentation',
            'version' => '1.0.0',
            'description' => '',
        ],
        'servers' => [],
        'route' => [
            'source' => self::ROUTE_SOURCE_AUTO,
            // When an endpoint has no @route, try to derive one from the
            // namespace and the class name (ThinkPHP pathinfo convention).
            'infer' => true,
            'thinkphp' => [
                'bootstrap' => 'vendor/autoload.php',
                'application' => null,
            ],
        ],
        // How a controller class name is turned into a URL segment.
        'url' => [
            // true  : app\api\controller\OrderController -> /api/order
            // false : app\api\controller\OrderController -> /api/ordercontroller
            // auto  : ask the framework (ThinkPHP defaults to false)
            'controller_suffix' => self::URL_CONVENTION_AUTO,
            // auto | lower | snake | kebab | keep
            'case' => self::URL_CONVENTION_AUTO,
        ],
        'security' => [
            'schemes' => [],
            'default' => [],
        ],
        'scan' => [
            'suffix' => 'Controller.php',
            'exclude' => ['vendor', 'node_modules', 'tests', 'runtime'],
        ],
        'tags' => [],
        'strict' => false,
        'fail_on_empty' => true,
        'reflection' => true,
        // Include controller methods that carry no apidoc tags at all.
        'include_undocumented' => false,
    ];

    /**
     * @var array<string, mixed>
     */
    private $data;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [], ?string $basePath = null)
    {
        $this->basePath = $basePath !== null && $basePath !== ''
            ? rtrim(Path::normalize($basePath), DIRECTORY_SEPARATOR)
            : Path::normalize((string) getcwd());

        $this->data = Arr::merge(self::DEFAULTS, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Arr::get($this->data, $key, $default);
    }

    /**
     * Returns a new instance with the given values merged on top.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        return new self(Arr::merge($this->data, $overrides), $this->basePath);
    }

    /**
     * Normalised list of controller directories to scan.
     *
     * @return list<array{path: string, namespace: ?string, prefix: string, exclude: list<string>, suffix: string}>
     */
    public function controllers(): array
    {
        $configured = $this->data['controllers'] ?? [];

        if (is_string($configured)) {
            $configured = [$configured];
        }

        if (!is_array($configured)) {
            throw ConfigException::invalid(
                '"controllers" must be a path string, a list of paths, or a list of arrays with a "path" key.'
            );
        }

        $result = [];

        foreach ($configured as $item) {
            if (is_string($item)) {
                $item = ['path' => $item];
            }

            if (!is_array($item)) {
                throw ConfigException::invalid('Every "controllers" entry must be a string or an array.');
            }

            $path = $item['path'] ?? null;

            if (!is_string($path) || trim($path) === '') {
                throw ConfigException::invalid('Every "controllers" entry must define a non empty "path".');
            }

            $suffix = $item['suffix'] ?? null;

            $result[] = [
                'path' => Path::resolve($this->basePath, $path),
                'namespace' => self::trimNamespace($item['namespace'] ?? null),
                'prefix' => is_string($item['prefix'] ?? null) ? $item['prefix'] : '',
                'exclude' => self::stringList($item['exclude'] ?? []),
                'suffix' => is_string($suffix) && $suffix !== '' ? $suffix : $this->fileSuffix(),
            ];
        }

        return $result;
    }

    /**
     * Normalised list of files to write.
     *
     * @return list<array{format: string, path: string}>
     */
    public function outputs(): array
    {
        $configured = $this->data['output'] ?? null;

        if (is_string($configured)) {
            $configured = [self::guessFormatFromPath($configured) => $configured];
        }

        if (is_array($configured) && !Arr::isAssoc($configured)) {
            $map = [];

            foreach ($configured as $path) {
                if (is_string($path) && $path !== '') {
                    $map[self::guessFormatFromPath($path)] = $path;
                }
            }

            $configured = $map;
        }

        if (!is_array($configured)) {
            throw ConfigException::invalid(
                '"output" must be a path string, a list of paths, or a map of format => path.'
            );
        }

        $outputs = [];

        foreach ([self::FORMAT_JSON, self::FORMAT_YAML] as $format) {
            $path = $configured[$format] ?? null;

            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $outputs[] = [
                'format' => $format,
                'path' => Path::resolve($this->basePath, $path),
            ];
        }

        if ($outputs === []) {
            throw ConfigException::invalid(
                'No output configured. Set "output.json" and/or "output.yaml" to a file path.'
            );
        }

        return $outputs;
    }

    /**
     * @return array{title: string, version: string, description: string}
     */
    public function info(): array
    {
        $info = $this->data['info'] ?? [];

        if (!is_array($info)) {
            $info = [];
        }

        return [
            'title' => self::stringOrDefault($info['title'] ?? null, 'API Documentation'),
            'version' => self::stringOrDefault($info['version'] ?? null, '1.0.0'),
            'description' => self::stringOrDefault($info['description'] ?? null, ''),
        ];
    }

    /**
     * @return list<string>
     */
    public function servers(): array
    {
        return self::stringList($this->data['servers'] ?? []);
    }

    public function routeSource(): string
    {
        $source = $this->data['route']['source'] ?? self::ROUTE_SOURCE_AUTO;

        $allowed = [self::ROUTE_SOURCE_AUTO, self::ROUTE_SOURCE_ANNOTATION, self::ROUTE_SOURCE_THINKPHP];

        if (!is_string($source) || !in_array($source, $allowed, true)) {
            throw ConfigException::invalid(sprintf(
                '"route.source" must be one of: %s. Got: %s.',
                implode(', ', $allowed),
                is_scalar($source) ? (string) $source : gettype($source)
            ));
        }

        return $source;
    }

    public function thinkPhpBootstrap(): ?string
    {
        return self::resolveOptionalPath(Arr::get($this->data, 'route.thinkphp.bootstrap'));
    }

    public function thinkPhpApplication(): ?string
    {
        return self::resolveOptionalPath(Arr::get($this->data, 'route.thinkphp.application'));
    }

    /**
     * Whether "Controller" is stripped from the controller URL segment.
     *
     * Returns the literal string "auto" when the framework route source should
     * be asked instead, which is the default.
     *
     * ThinkPHP's route.controller_suffix defaults to false, meaning the suffix
     * stays: "app\api\controller\OrderController" answers at
     * "/api/ordercontroller" and the document must say so.
     *
     * @return bool|string
     */
    public function controllerSuffix()
    {
        $value = Arr::get($this->data, 'url.controller_suffix', self::URL_CONVENTION_AUTO);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalised = strtolower(trim($value));

            if ($normalised === '' || $normalised === self::URL_CONVENTION_AUTO) {
                return self::URL_CONVENTION_AUTO;
            }

            if ($normalised === 'true') {
                return true;
            }

            if ($normalised === 'false') {
                return false;
            }
        }

        throw ConfigException::invalid(sprintf(
            '"url.controller_suffix" must be "auto", true or false. Got: %s.',
            is_scalar($value) ? (string) $value : gettype($value)
        ));
    }

    /**
     * The casing to spell URL segments with.
     *
     * "auto" means "ask the framework, and fall back to lower" and is the
     * default.
     */
    public function urlCase(): string
    {
        $value = Arr::get($this->data, 'url.case', self::URL_CONVENTION_AUTO);

        $allowed = [
            self::URL_CONVENTION_AUTO,
            self::CASE_LOWER,
            self::CASE_SNAKE,
            self::CASE_KEBAB,
            self::CASE_KEEP,
        ];

        if (is_string($value) && in_array(strtolower(trim($value)), $allowed, true)) {
            return strtolower(trim($value));
        }

        throw ConfigException::invalid(sprintf(
            '"url.case" must be one of: %s. Got: %s.',
            implode(', ', $allowed),
            is_scalar($value) ? (string) $value : gettype($value)
        ));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function securitySchemes(): array
    {
        $schemes = Arr::get($this->data, 'security.schemes', []);

        if (!is_array($schemes)) {
            return [];
        }

        $result = [];

        foreach ($schemes as $name => $definition) {
            if (is_string($name) && is_array($definition)) {
                $result[$name] = $definition;
            }
        }

        return $result;
    }

    /**
     * @return array<string, list<string>>
     */
    public function defaultSecurity(): array
    {
        $security = Arr::get($this->data, 'security.default', []);

        if (!is_array($security)) {
            return [];
        }

        $result = [];

        foreach ($security as $name => $scopes) {
            if (!is_string($name)) {
                continue;
            }

            $result[$name] = self::stringList(is_array($scopes) ? $scopes : []);
        }

        return $result;
    }

    /**
     * Tag name => description, accepting both map and list notation.
     *
     * @return array<string, string>
     */
    public function tagDescriptions(): array
    {
        $tags = $this->data['tags'] ?? [];

        if (!is_array($tags)) {
            return [];
        }

        $result = [];

        foreach ($tags as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $result[$key] = $value;

                continue;
            }

            if (is_array($value)) {
                $name = $value['name'] ?? null;

                if (is_string($name) && $name !== '') {
                    $result[$name] = self::stringOrDefault($value['description'] ?? null, '');
                }
            }
        }

        return $result;
    }

    public function fileSuffix(): string
    {
        return self::stringOrDefault(Arr::get($this->data, 'scan.suffix'), 'Controller.php');
    }

    /**
     * @return list<string>
     */
    public function excludePatterns(): array
    {
        return self::stringList(Arr::get($this->data, 'scan.exclude'));
    }

    public function isStrict(): bool
    {
        return (bool) ($this->data['strict'] ?? false);
    }

    public function failOnEmpty(): bool
    {
        return (bool) ($this->data['fail_on_empty'] ?? true);
    }

    /**
     * Whether PHP reflection may run to complete the documented parameters.
     */
    public function isReflectionEnabled(): bool
    {
        return (bool) ($this->data['reflection'] ?? true);
    }

    /**
     * Whether a missing @route may be derived from the namespace and class name.
     */
    public function inferRoutes(): bool
    {
        return (bool) Arr::get($this->data, 'route.infer', true);
    }

    /**
     * Whether methods without any apidoc tag should be documented as well.
     */
    public function includeUndocumented(): bool
    {
        return (bool) ($this->data['include_undocumented'] ?? false);
    }

    public static function guessFormatFromPath(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'yaml' || $extension === 'yml' ? self::FORMAT_YAML : self::FORMAT_JSON;
    }

    /**
     * @param mixed $value
     */
    private function resolveOptionalPath($value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return Path::resolve($this->basePath, $value);
    }

    /**
     * @param mixed $value
     *
     * @return list<string>
     */
    private static function stringList($value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param mixed $value
     */
    private static function stringOrDefault($value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @param mixed $value
     */
    private static function trimNamespace($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value, '\\ ');

        return $value === '' ? null : $value;
    }
}
