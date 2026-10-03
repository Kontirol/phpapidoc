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

    /**
     * ThinkPHP's route.controller_suffix defaults to false, so the suffix is
     * part of the URL: the class OrderController is reached at /api/ordercontroller.
     */
    public function testKeepsTheControllerSuffixByDefault(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller');

        self::assertSame(
            '/api/usercontroller/list',
            $inferrer->infer('app\\api\\controller\\UserController', 'list')
        );
    }

    public function testStripsTheControllerSuffixWhenTheApplicationDoes(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller', '', true);

        self::assertSame('/api/user/list', $inferrer->infer('app\\api\\controller\\UserController', 'list'));
    }

    /**
     * ThinkPHP lowercases the URL but does not split camel case, so
     * getUserInfo answers at /getuserinfo rather than /get_user_info.
     */
    public function testLowercasesCamelCaseWithoutSplittingIt(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller');

        self::assertSame('/api/userorder/getlist', $inferrer->infer('app\\api\\controller\\UserOrder', 'getList'));
    }

    public function testCanSplitCamelCaseIntoSeparateSnakeCaseWords(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller', '', false, UrlInferrer::CASE_SNAKE);

        self::assertSame('/api/user_order/get_list', $inferrer->infer('app\\api\\controller\\UserOrder', 'getList'));
    }

    public function testCanSplitCamelCaseIntoKebabCaseWords(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller', '', false, UrlInferrer::CASE_KEBAB);

        self::assertSame('/api/user-order/get-list', $inferrer->infer('app\\api\\controller\\UserOrder', 'getList'));
    }

    public function testCanKeepTheOriginalCase(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller', '', false, UrlInferrer::CASE_KEEP);

        self::assertSame('/api/UserOrder/getList', $inferrer->infer('app\\api\\controller\\UserOrder', 'getList'));
    }

    public function testAnUnknownCaseFallsBackToLower(): void
    {
        $inferrer = new UrlInferrer('app\\api\\controller', '', false, 'SNAKE_ISH');

        self::assertSame('/api/userorder/getlist', $inferrer->infer('app\\api\\controller\\UserOrder', 'getList'));
        self::assertSame(UrlInferrer::CASE_LOWER, $inferrer->segmentCase());
    }

    public function testExposesTheConfiguredConventions(): void
    {
        $default = new UrlInferrer('app\\api\\controller');

        self::assertFalse($default->stripsControllerSuffix());
        self::assertSame(UrlInferrer::CASE_LOWER, $default->segmentCase());

        $custom = new UrlInferrer('app\\api\\controller', '', true, UrlInferrer::CASE_KEBAB);

        self::assertTrue($custom->stripsControllerSuffix());
        self::assertSame(UrlInferrer::CASE_KEBAB, $custom->segmentCase());
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
        self::assertSame(
            '/api/usercontroller/index',
            $inferrer->infer('App\\Http\\Controllers\\UserController', 'index')
        );
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
