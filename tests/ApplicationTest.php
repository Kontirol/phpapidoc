<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests;

use Kontirol\ApiDoc\Application;
use Kontirol\ApiDoc\Config\Config;
use Kontirol\ApiDoc\Exception\OutputException;
use Kontirol\ApiDoc\Route\RouteDefinition;
use Kontirol\ApiDoc\Route\RouteSourceInterface;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private const INFER_CONTROLLER = 'Kontirol\\ApiDoc\\Tests\\Fixtures\\InferRoutes\\ProductController';

    /**
     * @var list<string>
     */
    private $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->files = [];
    }

    public function testRunsTheWholePipeline(): void
    {
        $output = $this->tempPath();

        $result = (new Application($this->config($output)))->run();

        self::assertFileExists($output);
        // UserController: index, detail, create (internal carries @ignore)
        // OrderController: detail | RoleController: index
        self::assertSame(5, $result->endpointCount());

        $spec = json_decode((string) file_get_contents($output), true);

        self::assertSame('Fixture API', $spec['info']['title']);
        self::assertSame('0.1.0', $spec['info']['version']);
        self::assertArrayHasKey('/user/list', $spec['paths']);
        self::assertArrayHasKey('/user/create', $spec['paths']);
        self::assertArrayHasKey('/order/{id}', $spec['paths']);
        self::assertArrayHasKey('/admin/role/list', $spec['paths']);
    }

    public function testSummaryDescriptionAndTagsSurviveThePipeline(): void
    {
        $document = (new Application($this->config($this->tempPath())))->buildDocument();

        $endpoint = $document->getEndpoint(
            'Kontirol\\ApiDoc\\Tests\\Fixtures\\Controllers\\UserController::index'
        );

        self::assertNotNull($endpoint);
        self::assertSame('用户列表', $endpoint->summary);
        self::assertSame('分页返回用户列表', $endpoint->description);
        self::assertSame(['用户'], $endpoint->tags);
        self::assertSame('GET', $endpoint->httpMethod);
    }

    public function testIgnoredEndpointsAreExcludedFromTheDocument(): void
    {
        $document = (new Application($this->config($this->tempPath())))->buildDocument();

        $ignored = $document->getEndpoint(
            'Kontirol\\ApiDoc\\Tests\\Fixtures\\Controllers\\UserController::internal'
        );

        self::assertNotNull($ignored);
        self::assertTrue($ignored->ignored);

        foreach ($document->documentedEndpoints() as $documented) {
            self::assertNotSame('internal', $documented->action);
        }
    }

    public function testOnlyMethodsWithAnEndpointDocBlockAreCollected(): void
    {
        $document = (new Application($this->config($this->tempPath())))->buildDocument();

        $actions = [];

        foreach ($document->endpoints as $endpoint) {
            if ($endpoint->shortController() === 'UserController') {
                $actions[] = $endpoint->action;
            }
        }

        sort($actions);

        self::assertSame(['create', 'detail', 'index', 'internal'], $actions);
    }

    public function testRouteSourceFillsInMissingRouteInformation(): void
    {
        $source = new class implements RouteSourceInterface {
            /**
             * @return list<RouteDefinition>
             */
            public function routes(): array
            {
                return [
                    new RouteDefinition(
                        'GET',
                        'api/user/list',
                        'Kontirol\\ApiDoc\\Tests\\Fixtures\\Controllers\\UserController',
                        'index'
                    ),
                ];
            }

            public function name(): string
            {
                return 'test';
            }
        };

        $document = (new Application($this->config($this->tempPath()), [$source]))->buildDocument();

        $endpoint = $document->getEndpoint(
            'Kontirol\\ApiDoc\\Tests\\Fixtures\\Controllers\\UserController::index'
        );

        self::assertNotNull($endpoint);
        self::assertSame('/api/user/list', $endpoint->route);
    }

    public function testEmptyResultThrowsWhenConfiguredToFail(): void
    {
        $config = new Config([
            'controllers' => 'Fixtures/EmptyControllers',
            'output' => ['json' => $this->tempPath()],
        ], __DIR__);

        $this->expectException(OutputException::class);
        $this->expectExceptionMessage('No endpoint was collected');

        (new Application($config))->run();
    }

    public function testEmptyResultIsWrittenWhenFailOnEmptyIsDisabled(): void
    {
        $output = $this->tempPath();

        $config = new Config([
            'controllers' => 'Fixtures/EmptyControllers',
            'output' => ['json' => $output],
            'fail_on_empty' => false,
        ], __DIR__);

        $result = (new Application($config))->run();

        self::assertSame(0, $result->endpointCount());
        self::assertFileExists($output);
    }

    public function testMissingRoutesAreDerivedFromTheConfiguredPrefix(): void
    {
        $document = (new Application($this->inferConfig()))->buildDocument();

        $index = $document->getEndpoint(self::INFER_CONTROLLER . '::index');

        self::assertNotNull($index);
        self::assertSame('/api/product/index', $index->route);
        self::assertTrue($index->extra['routeInferred'] ?? false);
    }

    public function testAWrittenRouteIsNeverOverwritten(): void
    {
        $document = (new Application($this->inferConfig()))->buildDocument();

        $detail = $document->getEndpoint(self::INFER_CONTROLLER . '::detail');

        self::assertNotNull($detail);
        self::assertSame('/custom/product/detail', $detail->route);
        self::assertArrayNotHasKey('routeInferred', $detail->extra);
    }

    public function testInferenceCanBeTurnedOff(): void
    {
        $document = (new Application($this->inferConfig()->with(['route' => ['infer' => false]])))->buildDocument();

        $index = $document->getEndpoint(self::INFER_CONTROLLER . '::index');

        self::assertNotNull($index);
        self::assertNull($index->route);
    }

    public function testInferredRoutesProduceANotice(): void
    {
        $document = (new Application($this->inferConfig()))->buildDocument();

        $codes = [];

        foreach ($document->diagnostics as $diagnostic) {
            $codes[] = $diagnostic->code;
        }

        self::assertContains('route.inferred', $codes);
    }

    public function testUndocumentedMethodsAreSkippedByDefault(): void
    {
        $document = (new Application($this->inferConfig()))->buildDocument();

        self::assertSame(2, count($document->documentedEndpoints()));
        self::assertNull($document->getEndpoint(self::INFER_CONTROLLER . '::save'));
    }

    public function testUndocumentedMethodsAreIncludedWhenConfigured(): void
    {
        $config = $this->inferConfig()->with(['include_undocumented' => true]);

        $document = (new Application($config))->buildDocument();

        self::assertSame(6, count($document->documentedEndpoints()));
    }

    public function testTheHttpMethodOfUndocumentedMethodsIsGuessedFromTheName(): void
    {
        $config = $this->inferConfig()->with(['include_undocumented' => true]);

        $document = (new Application($config))->buildDocument();

        $save = $document->getEndpoint(self::INFER_CONTROLLER . '::save');
        $remove = $document->getEndpoint(self::INFER_CONTROLLER . '::remove');
        $index = $document->getEndpoint(self::INFER_CONTROLLER . '::index');

        self::assertNotNull($save);
        self::assertNotNull($remove);
        self::assertNotNull($index);

        self::assertSame('POST', $save->httpMethod);
        self::assertSame('DELETE', $remove->httpMethod);
        self::assertSame('GET', $index->httpMethod);
        self::assertSame('save', $save->summary);
    }

    public function testUndocumentedMethodsAreReported(): void
    {
        $config = $this->inferConfig()->with(['include_undocumented' => true]);

        $document = (new Application($config))->buildDocument();

        $codes = [];

        foreach ($document->diagnostics as $diagnostic) {
            $codes[] = $diagnostic->code;
        }

        self::assertContains('endpoint.undocumented', $codes);
    }

    /**
     * @return Config
     */
    private function inferConfig(): Config
    {
        return new Config([
            'controllers' => [
                [
                    'path' => 'Fixtures/InferRoutes',
                    'namespace' => 'Kontirol\\ApiDoc\\Tests\\Fixtures\\InferRoutes',
                    'prefix' => '/api',
                ],
            ],
            'output' => ['json' => $this->tempPath()],
            'info' => ['title' => 'Inference Fixture', 'version' => '0.1.0'],
        ], __DIR__);
    }

    private function config(string $output): Config
    {
        return new Config([
            'controllers' => 'Fixtures/Controllers',
            'output' => ['json' => $output],
            'info' => ['title' => 'Fixture API', 'version' => '0.1.0'],
        ], __DIR__);
    }

    private function tempPath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apidoc_app_' . bin2hex(random_bytes(8)) . '.json';

        $this->files[] = $path;

        return $path;
    }
}
