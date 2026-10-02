<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Route;

use Kontirol\ApiDoc\Route\RouteDefinition;
use PHPUnit\Framework\TestCase;

final class RouteDefinitionTest extends TestCase
{
    public function testAddsLeadingSlash(): void
    {
        self::assertSame('/order/detail', RouteDefinition::normalizePath('order/detail'));
    }

    public function testKeepsExistingLeadingSlash(): void
    {
        self::assertSame('/order/detail', RouteDefinition::normalizePath('/order/detail'));
    }

    public function testConvertsColonPlaceholders(): void
    {
        self::assertSame('/order/{id}', RouteDefinition::normalizePath('order/:id'));
    }

    public function testConvertsAnglePlaceholders(): void
    {
        self::assertSame('/order/{id}/item/{itemId}', RouteDefinition::normalizePath('/order/<id>/item/<itemId>'));
    }

    public function testDropsQueryStringAndFragment(): void
    {
        self::assertSame('/order/detail', RouteDefinition::normalizePath('order/detail?page=1#top'));
    }

    public function testEmptyPathBecomesRoot(): void
    {
        self::assertSame('/', RouteDefinition::normalizePath(''));
    }

    public function testMethodIsUpperCased(): void
    {
        self::assertSame('GET', (new RouteDefinition('get', '/a'))->method);
        self::assertSame('*', (new RouteDefinition('', '/a'))->method);
    }

    public function testResolvedTargetsProduceAKey(): void
    {
        $route = new RouteDefinition('GET', '/a', 'App\\Controller\\Order', 'detail');

        self::assertTrue($route->isResolved());
        self::assertSame('App\\Controller\\Order::detail', $route->key());
    }

    public function testUnresolvedTargetsHaveNoKey(): void
    {
        $route = new RouteDefinition('GET', '/a');

        self::assertFalse($route->isResolved());
        self::assertNull($route->key());
    }
}
