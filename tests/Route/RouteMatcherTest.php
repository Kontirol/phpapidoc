<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Route;

use Kontirol\ApiDoc\Model\ApiEndpoint;
use Kontirol\ApiDoc\Model\Diagnostic;
use Kontirol\ApiDoc\Route\RouteDefinition;
use Kontirol\ApiDoc\Route\RouteMatcher;
use PHPUnit\Framework\TestCase;

final class RouteMatcherTest extends TestCase
{
    public function testFillsInMissingRouteInformation(): void
    {
        $endpoint = $this->endpoint('Order', 'detail');
        $matcher = new RouteMatcher();

        $result = $matcher->apply([$endpoint], [
            new RouteDefinition('GET', 'api/order/:id', 'App\\Controller\\Order', 'detail'),
        ]);

        self::assertSame('/api/order/{id}', $result[0]->route);
        self::assertSame('GET', $result[0]->httpMethod);
        self::assertSame([], $matcher->diagnostics());
    }

    public function testDocumentedRouteWinsWhenItMatches(): void
    {
        $endpoint = $this->endpoint('Order', 'detail');
        $endpoint->route = '/api/order/{id}';
        $endpoint->httpMethod = 'GET';

        $matcher = new RouteMatcher();

        $matcher->apply([$endpoint], [
            new RouteDefinition('GET', 'api/order/:id', 'App\\Controller\\Order', 'detail'),
        ]);

        self::assertSame('/api/order/{id}', $endpoint->route);
        self::assertSame([], $matcher->diagnostics());
    }

    public function testFrameworkRouteWinsAndMismatchesAreReported(): void
    {
        $endpoint = $this->endpoint('Order', 'detail');
        $endpoint->route = '/order/detail';
        $endpoint->httpMethod = 'POST';

        $matcher = new RouteMatcher();

        $matcher->apply([$endpoint], [
            new RouteDefinition('GET', 'api/order/:id', 'App\\Controller\\Order', 'detail'),
        ]);

        self::assertSame('/api/order/{id}', $endpoint->route);
        self::assertSame('GET', $endpoint->httpMethod);

        $codes = $this->codes($matcher);

        self::assertContains('route.mismatch', $codes);
        self::assertContains('route.method_mismatch', $codes);
    }

    public function testRoutesWithoutDocumentationAreReported(): void
    {
        $matcher = new RouteMatcher();

        $matcher->apply([], [
            new RouteDefinition('POST', 'order/create', 'App\\Controller\\Order', 'create'),
        ]);

        self::assertCount(1, $matcher->diagnostics());
        self::assertSame('route.undocumented', $matcher->diagnostics()[0]->code);
    }

    public function testClosureRoutesAreSkipped(): void
    {
        $matcher = new RouteMatcher();

        $matcher->apply([], [new RouteDefinition('GET', '/ping')]);

        self::assertSame([], $matcher->diagnostics());
    }

    public function testWildcardMethodKeepsTheDocumentedMethod(): void
    {
        $endpoint = $this->endpoint('Order', 'detail');
        $endpoint->httpMethod = 'POST';

        (new RouteMatcher())->apply([$endpoint], [
            new RouteDefinition('*', 'order/detail', 'App\\Controller\\Order', 'detail'),
        ]);

        self::assertSame('POST', $endpoint->httpMethod);
        self::assertSame('/order/detail', $endpoint->route);
    }

    public function testMiddlewareAndRouteNameAreKept(): void
    {
        $endpoint = $this->endpoint('Order', 'detail');

        $route = new RouteDefinition('GET', 'order/detail', 'App\\Controller\\Order', 'detail');
        $route->middleware = ['auth'];
        $route->name = 'order.detail';

        (new RouteMatcher())->apply([$endpoint], [$route]);

        self::assertSame(['auth'], $endpoint->extra['middleware']);
        self::assertSame('order.detail', $endpoint->extra['routeName']);
    }

    public function testRouteTargetsUsingShortControllerNamesAreMatched(): void
    {
        $endpoint = $this->endpoint('Order', 'detail');
        $matcher = new RouteMatcher();

        $matcher->apply([$endpoint], [
            new RouteDefinition('GET', 'api/order/:id', 'Order', 'detail'),
        ]);

        self::assertSame('/api/order/{id}', $endpoint->route);
        self::assertSame([], $matcher->diagnostics());
    }

    public function testComparisonIsCaseInsensitive(): void
    {
        $endpoint = $this->endpoint('Order', 'detail');
        $matcher = new RouteMatcher();

        $matcher->apply([$endpoint], [
            new RouteDefinition('get', 'order/detail', 'order', 'Detail'),
        ]);

        self::assertSame('/order/detail', $endpoint->route);
        self::assertSame('GET', $endpoint->httpMethod);
    }

    public function testAmbiguousShortNamesAreRefusedInsteadOfGuessed(): void
    {
        $api = new ApiEndpoint('App\\Api\\Controller\\Order', 'detail');
        $admin = new ApiEndpoint('App\\Admin\\Controller\\Order', 'detail');

        $matcher = new RouteMatcher();

        $matcher->apply([$api, $admin], [
            new RouteDefinition('GET', 'order/detail', 'Order', 'detail'),
        ]);

        self::assertNull($api->route);
        self::assertNull($admin->route);

        self::assertCount(1, $matcher->diagnostics());
        self::assertSame('route.ambiguous', $matcher->diagnostics()[0]->code);
        self::assertSame('warning', $matcher->diagnostics()[0]->level);
    }

    public function testAFullyQualifiedTargetStillMatchesWhenShortNamesCollide(): void
    {
        $api = new ApiEndpoint('App\\Api\\Controller\\Order', 'detail');
        $admin = new ApiEndpoint('App\\Admin\\Controller\\Order', 'detail');

        $matcher = new RouteMatcher();

        $matcher->apply([$api, $admin], [
            new RouteDefinition('GET', 'admin/order/detail', 'App\\Admin\\Controller\\Order', 'detail'),
        ]);

        self::assertSame('/admin/order/detail', $admin->route);
        self::assertNull($api->route);
    }

    private function endpoint(string $controller, string $action): ApiEndpoint
    {
        return new ApiEndpoint('App\\Controller\\' . $controller, $action);
    }

    /**
     * @return list<string>
     */
    private function codes(RouteMatcher $matcher): array
    {
        return array_map(static function (Diagnostic $diagnostic): string {
            return $diagnostic->code;
        }, $matcher->diagnostics());
    }
}
