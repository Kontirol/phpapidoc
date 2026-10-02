<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Route;

use Kontirol\ApiDoc\Route\UrlInferrer;
use PHPUnit\Framework\TestCase;

final class UrlInferrerTest extends TestCase
{
    public function testInfersTheMultiApplicationPrefixFromTheNamespace(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller');

        self::assertSame('/api/translate/text', $inferrer->infer('app\\api\\controller\\Translate', 'text'));
    }

    public function testInfersTheSingleApplicationPrefix(): void
    {
        $inferrer = new UrlInferrer('app\\controller');

        self::assertSame('/user/list', $inferrer->infer('app\\controller\\User', 'list'));
    }

    public function testStripsTheControllerSuffix(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller');

        self::assertSame('/api/user/list', $inferrer->infer('app\\api\\controller\\UserController', 'list'));
    }

    public function testConvertsCamelCaseToSnakeCase(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller');

        self::assertSame('/api/user_order/get_list', $inferrer->infer('app\\api\\controller\\UserOrder', 'getList'));
    }

    public function testNestedNamespaceSegmentsBecomePathSegments(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller');

        self::assertSame('/api/v1/user/list', $inferrer->infer('app\\api\\controller\\V1\\User', 'list'));
    }

    public function testAnExplicitPrefixWinsOverTheNamespace(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller', '/backend');

        self::assertSame('/backend/user/list', $inferrer->infer('app\\api\\controller\\User', 'list'));
    }

    public function testUnknownNamespacesProduceNothing(): void
    {
        $inferrer = new UrlInferrer('App\\Http\\Controllers');

        self::assertFalse($inferrer->isUsable());
        self::assertNull($inferrer->infer('App\\Http\\Controllers\\UserController', 'index'));
    }

    public function testAnExplicitPrefixWorksWithoutNamespaceKnowledge(): void
    {
        $inferrer = new UrlInferrer('App\\Http\\Controllers', '/api');

        self::assertTrue($inferrer->isUsable());
        self::assertSame('/api/user/index', $inferrer->infer('App\\Http\\Controllers\\UserController', 'index'));
    }

    public function testAnEmptyActionProducesTheControllerPath(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller');

        self::assertSame('/api/user', $inferrer->infer('app\\api\\controller\\User', ''));
    }

    public function testNoNamespaceAndNoPrefixIsUsable(): void
    {
        $inferrer = new UrlInferrer();

        self::assertNull($inferrer->infer('app\\controller\\User', 'index'));
    }

    public function testControllerOutsideTheConfiguredNamespaceKeepsItsSegments(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller');

        self::assertSame('/api/weird/other/thing', $inferrer->infer('Weird\\Other', 'thing'));
    }
}
