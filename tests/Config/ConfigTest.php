<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Config;

use Kontirol\ApiDoc\Config\Config;
use Kontirol\ApiDoc\Exception\ConfigException;
use Kontirol\ApiDoc\Support\Path;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private const BASE = 'D:/projects/demo';

    public function testDefaultsAreApplied(): void
    {
        $config = new Config([], self::BASE);

        self::assertSame('API Documentation', $config->info()['title']);
        self::assertSame('1.0.0', $config->info()['version']);
        self::assertSame('', $config->info()['description']);
        self::assertSame(Config::ROUTE_SOURCE_AUTO, $config->routeSource());
        self::assertSame('Controller.php', $config->fileSuffix());
        self::assertFalse($config->isStrict());
        self::assertTrue($config->failOnEmpty());
        self::assertSame([], $config->controllers());
        self::assertSame([], $config->servers());
        self::assertSame([], $config->securitySchemes());
        self::assertSame([], $config->defaultSecurity());
    }

    public function testOutputDefaultsToOpenApiJson(): void
    {
        $outputs = (new Config([], self::BASE))->outputs();

        self::assertCount(1, $outputs);
        self::assertSame(Config::FORMAT_JSON, $outputs[0]['format']);
        self::assertSame(Path::resolve(self::BASE, 'openapi.json'), $outputs[0]['path']);
    }

    public function testControllerPathAsStringIsNormalised(): void
    {
        $controllers = (new Config(['controllers' => 'app/api/controller'], self::BASE))->controllers();

        self::assertCount(1, $controllers);
        self::assertSame(Path::resolve(self::BASE, 'app/api/controller'), $controllers[0]['path']);
        self::assertNull($controllers[0]['namespace']);
        self::assertSame('', $controllers[0]['prefix']);
        self::assertSame('Controller.php', $controllers[0]['suffix']);
    }

    public function testControllerListOfPathsIsNormalised(): void
    {
        $config = new Config(['controllers' => ['app/api', 'app/backend']], self::BASE);

        self::assertCount(2, $config->controllers());
        self::assertSame(Path::resolve(self::BASE, 'app/backend'), $config->controllers()[1]['path']);
    }

    public function testControllerArrayFormKeepsNamespacePrefixAndSuffix(): void
    {
        $config = new Config([
            'controllers' => [
                [
                    'path' => 'app/api/controller',
                    'namespace' => '\\app\\api\\controller\\',
                    'prefix' => '/api',
                    'suffix' => 'Ctrl.php',
                    'exclude' => ['Base'],
                ],
            ],
        ], self::BASE);

        $controller = $config->controllers()[0];

        self::assertSame('app\\api\\controller', $controller['namespace']);
        self::assertSame('/api', $controller['prefix']);
        self::assertSame('Ctrl.php', $controller['suffix']);
        self::assertSame(['Base'], $controller['exclude']);
    }

    public function testControllerWithoutPathThrows(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('non empty "path"');

        (new Config(['controllers' => [['namespace' => 'app']]], self::BASE))->controllers();
    }

    public function testControllerWithInvalidTypeThrows(): void
    {
        $this->expectException(ConfigException::class);

        (new Config(['controllers' => 42], self::BASE))->controllers();
    }

    public function testOutputAsPlainStringUsesJsonFormat(): void
    {
        $outputs = (new Config(['output' => 'docs/api.json'], self::BASE))->outputs();

        self::assertCount(1, $outputs);
        self::assertSame(Config::FORMAT_JSON, $outputs[0]['format']);
    }

    public function testOutputAsListGuessesFormatFromExtension(): void
    {
        $outputs = (new Config(['output' => ['build/openapi.yaml', 'build/openapi.json']], self::BASE))->outputs();

        self::assertCount(2, $outputs);

        // Outputs are always ordered json first, then yaml, whatever the input order.
        self::assertSame(Config::FORMAT_JSON, $outputs[0]['format']);
        self::assertStringEndsWith('openapi.json', $outputs[0]['path']);
        self::assertSame(Config::FORMAT_YAML, $outputs[1]['format']);
        self::assertStringEndsWith('openapi.yaml', $outputs[1]['path']);
    }

    public function testOutputMapCanDisableYaml(): void
    {
        $outputs = (new Config([
            'output' => ['json' => 'api.json', 'yaml' => null],
        ], self::BASE))->outputs();

        self::assertCount(1, $outputs);
    }

    public function testMissingOutputThrows(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('No output configured');

        (new Config(['output' => ['json' => null, 'yaml' => null]], self::BASE))->outputs();
    }

    public function testWithReturnsNewInstanceAndMergesDeeply(): void
    {
        $config = new Config([], self::BASE);
        $updated = $config->with(['info' => ['title' => 'My API']]);

        self::assertNotSame($config, $updated);
        self::assertSame('API Documentation', $config->info()['title']);
        self::assertSame('My API', $updated->info()['title']);
        self::assertSame('1.0.0', $updated->info()['version']);
    }

    public function testInvalidRouteSourceThrows(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('route.source');

        (new Config(['route' => ['source' => 'nope']], self::BASE))->routeSource();
    }

    public function testThinkPhpPathsAreResolvedAgainstBasePath(): void
    {
        $config = new Config([
            'route' => ['source' => 'thinkphp', 'thinkphp' => ['bootstrap' => 'vendor/autoload.php']],
        ], self::BASE);

        self::assertSame(Config::ROUTE_SOURCE_THINKPHP, $config->routeSource());
        self::assertSame(Path::resolve(self::BASE, 'vendor/autoload.php'), $config->thinkPhpBootstrap());
        self::assertNull($config->thinkPhpApplication());
    }

    public function testSecurityConfigurationIsNormalised(): void
    {
        $config = new Config([
            'security' => [
                'schemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer'],
                ],
                'default' => ['bearerAuth' => []],
            ],
        ], self::BASE);

        self::assertArrayHasKey('bearerAuth', $config->securitySchemes());
        self::assertSame(['bearerAuth' => []], $config->defaultSecurity());
    }

    public function testTagDescriptionsAcceptMapAndListNotation(): void
    {
        $config = new Config([
            'tags' => [
                'order' => '订单管理',
                ['name' => 'user', 'description' => '用户中心'],
            ],
        ], self::BASE);

        $tags = $config->tagDescriptions();

        self::assertSame('订单管理', $tags['order']);
        self::assertSame('用户中心', $tags['user']);
    }

    public function testDotNotationAccessAndFallback(): void
    {
        $config = new Config(['scan' => ['suffix' => 'Api.php']], self::BASE);

        self::assertSame('Api.php', $config->get('scan.suffix'));
        self::assertSame('fallback', $config->get('scan.missing', 'fallback'));
        self::assertSame('fallback', $config->get('nope.nope.nope', 'fallback'));
    }

    public function testBasePathDefaultsToCurrentWorkingDirectory(): void
    {
        $config = new Config([]);

        self::assertSame(Path::normalize((string) getcwd()), $config->basePath());
    }
}
