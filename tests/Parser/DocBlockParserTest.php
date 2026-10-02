<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Parser;

use Kontirol\ApiDoc\Parser\DocBlockParser;
use PHPUnit\Framework\TestCase;

final class DocBlockParserTest extends TestCase
{
    /**
     * @var DocBlockParser
     */
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new DocBlockParser();
    }

    public function testParsesTagsFromAMultiLineDocBlock(): void
    {
        $docBlock = <<<'BLOCK'
/**
 * @name 用户列表
 * @desc 分页返回用户列表
 * @route /user/list
 * @method GET
 */
BLOCK;

        $parsed = $this->parser->parse($docBlock, 10);

        self::assertTrue($parsed->has('name'));
        self::assertSame('用户列表', $parsed->value('name'));
        self::assertSame('分页返回用户列表', $parsed->value('desc'));
        self::assertSame('/user/list', $parsed->value('route'));
        self::assertSame('GET', $parsed->value('method'));
    }

    public function testParsesSingleLineDocBlock(): void
    {
        $parsed = $this->parser->parse('/** @name 测试接口 @method GET */');

        self::assertSame('测试接口 @method GET', $parsed->value('name'));
    }

    public function testKeepsEveryOccurrenceOfRepeatedTags(): void
    {
        $docBlock = <<<'BLOCK'
/**
 * @param int page 页码
 * @param int pageSize 每页数量
 * @response 201 {"code":200}
 * @response 400 {"code":400}
 */
BLOCK;

        $parsed = $this->parser->parse($docBlock);

        self::assertCount(2, $parsed->all('param'));
        self::assertCount(2, $parsed->all('response'));
        self::assertSame('int page 页码', $parsed->first('param')->value);
        self::assertSame('201 {"code":200}', $parsed->first('response')->value);
    }

    public function testJoinsContinuationLines(): void
    {
        $docBlock = <<<'BLOCK'
/**
 * @desc 第一行
 * 第二行
 * @method GET
 */
BLOCK;

        $parsed = $this->parser->parse($docBlock);

        self::assertSame("第一行\n第二行", $parsed->value('desc'));
        self::assertSame('GET', $parsed->value('method'));
    }

    public function testReportsAbsoluteLineNumbers(): void
    {
        $docBlock = <<<'BLOCK'
/**
 * @name 用户列表
 * @route /user/list
 */
BLOCK;

        $parsed = $this->parser->parse($docBlock, 42);

        self::assertSame(43, $parsed->first('name')->line);
        self::assertSame(44, $parsed->first('route')->line);
    }

    public function testTagNamesAreCaseInsensitive(): void
    {
        $parsed = $this->parser->parse("/**\n * @Name 用户列表\n */");

        self::assertTrue($parsed->has('name'));
        self::assertSame('用户列表', $parsed->value('NAME'));
    }

    public function testIgnoresTextBeforeTheFirstTag(): void
    {
        $parsed = $this->parser->parse("/**\n * 这是一段说明\n * @method GET\n */");

        self::assertSame('GET', $parsed->value('method'));
        self::assertFalse($parsed->has('这是一段说明'));
    }

    public function testEmptyDocBlockProducesNoTags(): void
    {
        self::assertTrue($this->parser->parse('/** */')->isEmpty());
        self::assertTrue($this->parser->parse('')->isEmpty());
    }

    public function testKeepsMultiByteCharactersIntact(): void
    {
        // "情" is E6 83 85 in UTF-8: the trailing 0x85 byte is what \R used to
        // mistake for a line break, silently chopping the value in half.
        $docBlock = "/**\n * @name 用户详情\n * @desc 获取订单详情\n */";

        $parsed = $this->parser->parse($docBlock, 1);

        self::assertSame('用户详情', $parsed->value('name'));
        self::assertSame('获取订单详情', $parsed->value('desc'));
        self::assertTrue(mb_check_encoding($parsed->value('name'), 'UTF-8'));
    }

    public function testDefaultIsReturnedForMissingTags(): void
    {
        $parsed = $this->parser->parse("/**\n * @method GET\n */");

        self::assertSame('fallback', $parsed->value('route', 'fallback'));
        self::assertNull($parsed->first('route'));
        self::assertSame([], $parsed->all('route'));
    }
}
