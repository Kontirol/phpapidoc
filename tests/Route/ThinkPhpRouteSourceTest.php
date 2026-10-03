<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Route;

use Kontirol\ApiDoc\Exception\RouteException;
use Kontirol\ApiDoc\Route\ThinkPhpRouteSource;
use PHPUnit\Framework\TestCase;

final class ThinkPhpRouteSourceTest extends TestCase
{
    public function testReadsAFlatRuleList(): void
    {
        $source = $this->source([
            'order/detail' => $this->ruleItem('order/detail', 'Order/detail', 'GET'),
        ]);

        $routes = $source->routes();

        self::assertCount(1, $routes);
        self::assertSame('GET', $routes[0]->method);
        self::assertSame('/order/detail', $routes[0]->path);
        self::assertSame('Order', $routes[0]->controller);
        self::assertSame('detail', $routes[0]->action);
        self::assertSame('Order::detail', $routes[0]->key());
    }

    public function testWalksNestedRuleGroups(): void
    {
        $source = $this->source([
            'api' => [
                'v1' => [
                    'user/list' => $this->ruleItem('user/list', 'User/index', 'GET'),
                    'user/create' => $this->ruleItem('user/create', 'User/create', 'POST'),
                ],
            ],
        ]);

        $routes = $source->routes();

        self::assertCount(2, $routes);
        self::assertSame(['/user/list', '/user/create'], [$routes[0]->path, $routes[1]->path]);
    }

    public function testHandlesRuleGroupsKeyedByMethod(): void
    {
        $source = $this->source([
            'user' => [
                'GET' => $this->ruleItem('user', 'User/read', 'GET'),
                'POST' => $this->ruleItem('user', 'User/create', 'POST'),
            ],
        ]);

        $routes = $source->routes();

        self::assertCount(2, $routes);
        self::assertSame('GET', $routes[0]->method);
        self::assertSame('POST', $routes[1]->method);
    }

    public function testReadsTheAtSyntaxForTargets(): void
    {
        $source = $this->source([
            $this->ruleItem('order/detail', 'app\\api\\controller\\Order@detail', 'GET'),
        ]);

        $routes = $source->routes();

        self::assertSame('app\\api\\controller\\Order', $routes[0]->controller);
        self::assertSame('detail', $routes[0]->action);
    }

    public function testReadsArrayTargets(): void
    {
        $source = $this->source([
            $this->ruleItem('order/detail', ['App\\Api\\Controller\\Order', 'detail'], 'GET'),
        ]);

        $routes = $source->routes();

        self::assertSame('App\\Api\\Controller\\Order', $routes[0]->controller);
        self::assertSame('detail', $routes[0]->action);
    }

    public function testClosureRoutesKeepTheirPathButHaveNoTarget(): void
    {
        $source = $this->source([
            $this->ruleItem('ping', static function (): void {
            }),
        ]);

        $routes = $source->routes();

        self::assertCount(1, $routes);
        self::assertSame('/ping', $routes[0]->path);
        self::assertFalse($routes[0]->isResolved());
        self::assertNull($routes[0]->key());
    }

    public function testReadsRouteNameAndMiddleware(): void
    {
        $source = $this->source([
            $this->ruleItem('order/detail', 'Order/detail', 'GET', 'order.detail', ['auth', 'throttle']),
        ]);

        $route = $source->routes()[0];

        self::assertSame('order.detail', $route->name);
        self::assertSame(['auth', 'throttle'], $route->middleware);
    }

    public function testUnknownObjectsAndScalarsAreIgnored(): void
    {
        $source = $this->source([
            new \stdClass(),
            'a string',
            42,
            null,
            [],
            $this->ruleItem('', 'Order/detail', 'GET'),
            $this->ruleItem('   ', 'Order/detail', 'GET'),
        ]);

        self::assertSame([], $source->routes());
    }

    public function testTraversableContainersAreWalked(): void
    {
        $source = $this->source(new \ArrayIterator([
            'order/detail' => $this->ruleItem('order/detail', 'Order/detail', 'GET'),
        ]));

        self::assertCount(1, $source->routes());
    }

    public function testDeeplyNestedStructuresDoNotRecurseForever(): void
    {
        $nested = [];

        for ($index = 0; $index < 30; $index++) {
            $nested = [$nested];
        }

        $source = $this->source($nested);

        self::assertSame([], $source->routes());
    }

    public function testMissingBootstrapFileIsReported(): void
    {
        $source = new ThinkPhpRouteSource(__DIR__ . '/does-not-exist/autoload.php');

        $this->expectException(RouteException::class);
        $this->expectExceptionMessage('bootstrap');

        $source->routes();
    }

    public function testReadsTheArrayShapeReturnedByThinkPhp8(): void
    {
        $source = $this->source([
            [
                'method' => 'get',
                'rule' => 'user/list',
                'name' => 'user.list',
                'route' => 'User/index',
                'domain' => '-',
                'pattern' => [],
                'option' => ['middleware' => ['auth'], 'remove_slash' => false],
            ],
        ]);

        $routes = $source->routes();

        self::assertCount(1, $routes);
        self::assertSame('GET', $routes[0]->method);
        self::assertSame('/user/list', $routes[0]->path);
        self::assertSame('User', $routes[0]->controller);
        self::assertSame('index', $routes[0]->action);
        self::assertSame('user.list', $routes[0]->name);
        self::assertSame(['auth'], $routes[0]->middleware);
    }

    public function testSkipsTheBuiltInMissRoute(): void
    {
        $source = $this->source([
            [
                'method' => 'options',
                'rule' => '/<MISS>',
                'name' => '',
                'route' => static function (): void {
                },
                'domain' => '-',
                'pattern' => [],
                'option' => [
                    'remove_slash' => false,
                    'model' => [],
                    'append' => [],
                    'middleware' => [],
                ],
            ],
        ]);

        self::assertSame([], $source->routes());
    }

    public function testWalksDomainsAndNestedListsOfRuleEntries(): void
    {
        $source = $this->source([
            'example.com' => [
                ['method' => 'get', 'rule' => 'a', 'route' => 'A/index', 'name' => '', 'option' => []],
                ['method' => 'post', 'rule' => 'b', 'route' => 'B/create', 'name' => '', 'option' => []],
            ],
        ]);

        $routes = $source->routes();

        self::assertCount(2, $routes);
        self::assertSame(['/a', '/b'], [$routes[0]->path, $routes[1]->path]);
    }

    public function testArrayEntriesWithoutATargetStayUnresolved(): void
    {
        $source = $this->source([
            [
                'method' => 'get',
                'rule' => 'ping',
                'route' => static function (): void {
                },
                'name' => '',
                'option' => [],
            ],
        ]);

        $routes = $source->routes();

        self::assertCount(1, $routes);
        self::assertSame('/ping', $routes[0]->path);
        self::assertFalse($routes[0]->isResolved());
    }

    public function testNameIsStable(): void
    {
        self::assertSame('thinkphp', $this->source([])->name());
    }

    /**
     * Nothing can be reported about a framework that was never bootstrapped, and
     * saying so must not throw.
     */
    public function testUrlConventionsAreUnknownWithoutABootstrappedFramework(): void
    {
        $source = $this->source([]);

        self::assertSame(
            ['controllerSuffix' => null, 'urlCase' => null],
            $source->urlConventions()
        );
    }

    public function testTheRuleListIsOnlyFetchedOnce(): void
    {
        $calls = 0;

        $source = new ThinkPhpRouteSource(null, static function () use (&$calls): array {
            $calls++;

            return [];
        });

        $source->routes();
        $source->routes();
        $source->urlConventions();

        self::assertSame(1, $calls);
    }

    /**
     * @param mixed $rules
     */
    private function source($rules): ThinkPhpRouteSource
    {
        return new ThinkPhpRouteSource(null, static function () use ($rules) {
            return is_array($rules) ? $rules : [$rules];
        });
    }

    /**
     * A stand-in for think\route\RuleItem, exposing the same accessors.
     *
     * @param mixed        $target
     * @param list<string> $middleware
     */
    private function ruleItem(
        string $rule,
        $target,
        string $method = '*',
        string $name = '',
        array $middleware = []
    ): object {
        return new class($rule, $target, $method, $name, $middleware) {
            /**
             * @var string
             */
            private $rule;

            /**
             * @var mixed
             */
            private $target;

            /**
             * @var string
             */
            private $method;

            /**
             * @var string
             */
            private $name;

            /**
             * @var list<string>
             */
            private $middleware;

            /**
             * @param mixed        $target
             * @param list<string> $middleware
             */
            public function __construct(
                string $rule,
                $target,
                string $method,
                string $name,
                array $middleware
            ) {
                $this->rule = $rule;
                $this->target = $target;
                $this->method = $method;
                $this->name = $name;
                $this->middleware = $middleware;
            }

            public function getRule(): string
            {
                return $this->rule;
            }

            /**
             * @return mixed
             */
            public function getRoute()
            {
                return $this->target;
            }

            public function getMethod(): string
            {
                return $this->method;
            }

            public function getName(): string
            {
                return $this->name;
            }

            /**
             * @return list<string>
             */
            public function getMiddleware(): array
            {
                return $this->middleware;
            }
        };
    }
}
