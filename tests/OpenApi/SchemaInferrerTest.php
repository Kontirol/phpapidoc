<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\OpenApi;

use Kontirol\ApiDoc\OpenApi\SchemaInferrer;
use PHPUnit\Framework\TestCase;

final class SchemaInferrerTest extends TestCase
{
    /**
     * @var SchemaInferrer
     */
    private $inferrer;

    protected function setUp(): void
    {
        $this->inferrer = new SchemaInferrer();
    }

    public function testInfersScalars(): void
    {
        self::assertSame(['type' => 'string'], $this->inferrer->infer('x'));
        self::assertSame(['type' => 'integer'], $this->inferrer->infer(1));
        self::assertSame(['type' => 'number'], $this->inferrer->infer(1.5));
        self::assertSame(['type' => 'boolean'], $this->inferrer->infer(true));
        self::assertSame(['nullable' => true], $this->inferrer->infer(null));
    }

    public function testInfersObjectWithProperties(): void
    {
        $schema = $this->inferrer->infer(['code' => 200, 'data' => ['id' => 1, 'name' => '张三']]);

        self::assertSame('object', $schema['type']);
        self::assertSame(['type' => 'integer'], $schema['properties']['code']);
        self::assertSame('object', $schema['properties']['data']['type']);
        self::assertSame(['type' => 'string'], $schema['properties']['data']['properties']['name']);
    }

    public function testInfersArrayOfObjectsFromTheFirstItem(): void
    {
        $schema = $this->inferrer->infer([['id' => 1], ['id' => 2]]);

        self::assertSame('array', $schema['type']);
        self::assertSame('object', $schema['items']['type']);
        self::assertSame(['type' => 'integer'], $schema['items']['properties']['id']);
    }

    public function testInfersArrayOfScalars(): void
    {
        $schema = $this->inferrer->infer([1, 2, 3]);

        self::assertSame('array', $schema['type']);
        self::assertSame(['type' => 'integer'], $schema['items']);
    }

    public function testEmptyArrayIsDocumentedAsAnArray(): void
    {
        $schema = $this->inferrer->infer([]);

        self::assertSame('array', $schema['type']);
        self::assertArrayHasKey('items', $schema);
    }

    public function testNestedListsAreDetected(): void
    {
        self::assertTrue(SchemaInferrer::isList(['a', 'b']));
        self::assertFalse(SchemaInferrer::isList(['k' => 'v']));
        self::assertFalse(SchemaInferrer::isList([1 => 'a', 3 => 'b']));
    }
}
