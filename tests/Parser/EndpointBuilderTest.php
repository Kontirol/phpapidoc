<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Parser;

use Kontirol\ApiDoc\Model\ApiEndpoint;
use Kontirol\ApiDoc\Model\Parameter;
use Kontirol\ApiDoc\Parser\EndpointBuilder;
use Kontirol\ApiDoc\Parser\SourceScanner;
use PHPUnit\Framework\TestCase;

final class EndpointBuilderTest extends TestCase
{
    /**
     * @var EndpointBuilder
     */
    private $builder;

    protected function setUp(): void
    {
        $this->builder = new EndpointBuilder();
    }

    public function testBuildsAnEndpointFromADocBlock(): void
    {
        $endpoint = $this->fromFixture('UserController.php', 'index');

        self::assertNotNull($endpoint);
        self::assertSame('用户列表', $endpoint->summary);
        self::assertSame('分页返回用户列表', $endpoint->description);
        self::assertSame('/user/list', $endpoint->route);
        self::assertSame('GET', $endpoint->httpMethod);
        self::assertSame(['用户'], $endpoint->tags);
        self::assertSame(
            'Kontirol\\ApiDoc\\Tests\\Fixtures\\Controllers\\UserController::index',
            $endpoint->id()
        );
    }

    public function testParsesTypedParameters(): void
    {
        $endpoint = $this->fromFixture('UserController.php', 'index');

        self::assertNotNull($endpoint);
        self::assertCount(2, $endpoint->parameters);

        $page = $endpoint->getParameter('page');

        self::assertNotNull($page);
        self::assertSame('integer', $page->type);
        self::assertSame('页码，默认1', $page->description);
        self::assertSame(Parameter::IN_QUERY, $page->in);
        self::assertFalse($page->required);
    }

    public function testParsesResponseExample(): void
    {
        $endpoint = $this->fromFixture('UserController.php', 'index');

        self::assertNotNull($endpoint);
        self::assertCount(1, $endpoint->responses);

        $response = $endpoint->responses[0];

        self::assertSame('200', $response->statusCode);
        self::assertTrue($response->hasExample);
        self::assertSame(200, $response->example['code']);
    }

    public function testReadsAuthTag(): void
    {
        $endpoint = $this->fromFixture('UserController.php', 'detail');

        self::assertNotNull($endpoint);
        self::assertSame('bearer', $endpoint->auth);
    }

    public function testReadsBodyAndMultipleResponses(): void
    {
        $endpoint = $this->fromFixture('UserController.php', 'create');

        self::assertNotNull($endpoint);
        self::assertTrue($endpoint->hasBody());
        self::assertTrue($endpoint->requestBody->hasExample);
        self::assertSame('张三', $endpoint->requestBody->example['name']);

        self::assertCount(2, $endpoint->responses);
        self::assertSame('201', $endpoint->responses[0]->statusCode);
        self::assertSame('400', $endpoint->responses[1]->statusCode);
    }

    public function testIgnoreTagMarksTheEndpointAsExcluded(): void
    {
        $endpoint = $this->fromFixture('UserController.php', 'internal');

        self::assertNotNull($endpoint);
        self::assertTrue($endpoint->ignored);
    }

    public function testMethodWithoutDocBlockIsSkipped(): void
    {
        self::assertNull($this->fromFixture('UserController.php', 'notDocumented'));
    }

    public function testRoutePlaceholderBecomesARequiredPathParameter(): void
    {
        $endpoint = $this->fromFixture('OrderController.php', 'detail');

        self::assertNotNull($endpoint);
        self::assertSame('/order/{id}', $endpoint->route);

        $id = $endpoint->getParameter('id');

        self::assertNotNull($id);
        self::assertTrue($id->isInPath());
        self::assertTrue($id->required);
    }

    public function testRoutePlaceholderWithoutParamIsGenerated(): void
    {
        $endpoint = $this->fromCode(<<<'CODE'
<?php

class Foo
{
    /**
     * @name 详情
     * @route /thing/{code}
     * @method GET
     */
    public function show(): void {}
}
CODE);

        self::assertNotNull($endpoint);

        $code = $endpoint->getParameter('code');

        self::assertNotNull($code);
        self::assertTrue($code->isInPath());
        self::assertTrue($code->required);
        self::assertSame('string', $code->type);
    }

    public function testAddsDefaultResponseWhenNoneDocumented(): void
    {
        $endpoint = $this->fromCode(<<<'CODE'
<?php

class Foo
{
    /**
     * @name 测试
     * @route /test
     * @method GET
     */
    public function index(): void {}
}
CODE);

        self::assertNotNull($endpoint);
        self::assertCount(1, $endpoint->responses);
        self::assertSame('200', $endpoint->responses[0]->statusCode);
        self::assertFalse($endpoint->responses[0]->hasExample);
    }

    public function testDocBlockWithoutKnownTagsIsNotAnEndpoint(): void
    {
        $endpoint = $this->fromCode(<<<'CODE'
<?php

class Foo
{
    /**
     * 只是普通的说明文字
     *
     * @desc 有描述但没有接口标签
     */
    public function helper(): void {}
}
CODE);

        self::assertNull($endpoint);
    }

    public function testClassDocBlockIsNeverAnEndpoint(): void
    {
        self::assertNull($this->fromFixture('UserController.php', '__class__'));
    }

    public function testDiagnosticsAreCollectedForMalformedTags(): void
    {
        $builder = new EndpointBuilder();

        $this->buildFromCodeWith($builder, <<<'CODE'
<?php

class Foo
{
    /**
     * @name 测试
     * @route /test
     * @param int
     */
    public function index(): void {}
}
CODE);

        self::assertNotEmpty($builder->diagnostics());
        self::assertSame('param.malformed', $builder->diagnostics()[0]->code);
    }

    private function fromFixture(string $fixture, string $method): ?ApiEndpoint
    {
        $path = __DIR__ . '/../Fixtures/Controllers/' . $fixture;
        $code = (string) file_get_contents($path);

        return $this->buildFirst($code, $method);
    }

    private function fromCode(string $code): ?ApiEndpoint
    {
        return $this->buildFirst($code, null);
    }

    private function buildFromCodeWith(EndpointBuilder $builder, string $code): ?ApiEndpoint
    {
        $classes = (new SourceScanner())->scan($code);

        if ($classes === [] || $classes[0]->methods === []) {
            return null;
        }

        $class = $classes[0];

        return $builder->build($class->fqcn(), $class->methods[0], 'inline');
    }

    private function buildFirst(string $code, ?string $method): ?ApiEndpoint
    {
        $classes = (new SourceScanner())->scan($code);

        foreach ($classes as $class) {
            foreach ($class->methods as $parsed) {
                if ($method === null || $parsed->name === $method) {
                    return $this->builder->build($class->fqcn(), $parsed, 'inline');
                }
            }
        }

        return null;
    }
}
