<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Reflector;

use Kontirol\ApiDoc\Model\ApiEndpoint;
use Kontirol\ApiDoc\Model\Parameter;
use Kontirol\ApiDoc\Reflector\ReflectionEnricher;
use Kontirol\ApiDoc\Tests\Fixtures\Reflection\ProductController;
use PHPUnit\Framework\TestCase;

final class ReflectionEnricherTest extends TestCase
{
    public function testAddsParametersFoundInTheMethodSignature(): void
    {
        $endpoint = $this->endpoint('index', [new Parameter('categoryId', 'integer', '分类 ID')]);

        (new ReflectionEnricher())->enrich($endpoint);

        self::assertTrue($endpoint->hasParameter('page'));
        self::assertTrue($endpoint->hasParameter('keyword'));
        self::assertTrue($endpoint->hasParameter('categoryId'));
        self::assertCount(3, $endpoint->parameters);
    }

    public function testMapsBuiltinTypes(): void
    {
        $endpoint = $this->endpoint('index', []);

        (new ReflectionEnricher())->enrich($endpoint);

        self::assertSame('integer', $endpoint->getParameter('page')->type);
        self::assertSame('string', $endpoint->getParameter('keyword')->type);
    }

    public function testParametersWithDefaultValuesAreOptional(): void
    {
        $endpoint = $this->endpoint('index', []);

        (new ReflectionEnricher())->enrich($endpoint);

        $page = $endpoint->getParameter('page');

        self::assertNotNull($page);
        self::assertFalse($page->required);
        self::assertTrue($page->hasDefault);
        self::assertSame(1, $page->default);
    }

    public function testParametersWithoutDefaultValuesAreRequired(): void
    {
        $endpoint = $this->endpoint('upload', []);

        (new ReflectionEnricher())->enrich($endpoint);

        $name = $endpoint->getParameter('name');

        self::assertNotNull($name);
        self::assertTrue($name->required);
        self::assertFalse($name->hasDefault);
        self::assertSame('string', $name->type);
    }

    public function testClassTypedArgumentsAreIgnored(): void
    {
        $endpoint = $this->endpoint('upload', []);

        (new ReflectionEnricher())->enrich($endpoint);

        self::assertFalse($endpoint->hasParameter('payload'));
        self::assertTrue($endpoint->hasParameter('name'));
        self::assertTrue($endpoint->hasParameter('draft'));
    }

    public function testUntypedArgumentsAreIgnored(): void
    {
        $enricher = new ReflectionEnricher();
        $endpoint = $this->endpoint('legacy', []);

        $enricher->enrich($endpoint);

        self::assertFalse($endpoint->hasParameter('anything'));
        self::assertSame([], $endpoint->parameters);
        self::assertSame([], $enricher->diagnostics());
    }

    public function testAlreadyDocumentedParametersAreLeftUntouched(): void
    {
        $endpoint = $this->endpoint('index', [new Parameter('page', 'string', '我自己的说明')]);

        (new ReflectionEnricher())->enrich($endpoint);

        $page = $endpoint->getParameter('page');

        self::assertNotNull($page);
        self::assertSame('我自己的说明', $page->description);
        self::assertSame('string', $page->type);
    }

    public function testUnknownClassIsIgnoredSilently(): void
    {
        $enricher = new ReflectionEnricher();
        $endpoint = new ApiEndpoint('App\\Does\\Not\\Exist', 'index');

        $enricher->enrich($endpoint);

        self::assertSame([], $endpoint->parameters);
        self::assertSame([], $enricher->diagnostics());
    }

    public function testUnknownMethodIsIgnoredSilently(): void
    {
        $enricher = new ReflectionEnricher();
        $endpoint = new ApiEndpoint(ProductController::class, 'doesNotExist');

        $enricher->enrich($endpoint);

        self::assertSame([], $enricher->diagnostics());
    }

    public function testUndocumentedParametersProduceANotice(): void
    {
        $enricher = new ReflectionEnricher();

        $enricher->enrich($this->endpoint('index', [new Parameter('categoryId', 'integer', '分类 ID')]));

        self::assertCount(2, $enricher->diagnostics());
        self::assertSame('param.undocumented', $enricher->diagnostics()[0]->code);
        self::assertSame('notice', $enricher->diagnostics()[0]->level);
    }

    /**
     * @param list<Parameter> $parameters
     */
    private function endpoint(string $action, array $parameters): ApiEndpoint
    {
        $endpoint = new ApiEndpoint(ProductController::class, $action, 'fixture', 1);

        foreach ($parameters as $parameter) {
            $endpoint->addParameter($parameter);
        }

        return $endpoint;
    }
}
