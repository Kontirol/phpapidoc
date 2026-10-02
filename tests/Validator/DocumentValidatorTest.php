<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Validator;

use Kontirol\ApiDoc\Model\ApiDocument;
use Kontirol\ApiDoc\Model\ApiEndpoint;
use Kontirol\ApiDoc\Validator\DocumentValidator;
use PHPUnit\Framework\TestCase;

final class DocumentValidatorTest extends TestCase
{
    public function testCleanDocumentProducesNoDiagnostics(): void
    {
        $document = new ApiDocument();
        $document->addEndpoint($this->endpoint('User', 'index', '/user/list'));

        self::assertSame([], (new DocumentValidator())->validate($document));
    }

    public function testReportsEndpointsWithoutRoute(): void
    {
        $document = new ApiDocument();
        $document->addEndpoint($this->endpoint('User', 'index', null));

        $diagnostics = (new DocumentValidator())->validate($document);

        self::assertCount(1, $diagnostics);
        self::assertSame('endpoint.no_route', $diagnostics[0]->code);
        self::assertSame('warning', $diagnostics[0]->level);
    }

    public function testReportsDuplicateOperations(): void
    {
        $document = new ApiDocument();
        $document->addEndpoint($this->endpoint('User', 'index', '/user/list'));
        $document->addEndpoint($this->endpoint('Admin', 'index', '/user/list'));

        $diagnostics = (new DocumentValidator())->validate($document);

        self::assertCount(1, $diagnostics);
        self::assertSame('endpoint.duplicate_route', $diagnostics[0]->code);
        self::assertSame('error', $diagnostics[0]->level);
        self::assertStringContainsString('GET /user/list', $diagnostics[0]->message);
    }

    public function testTheSamePathWithDifferentMethodsIsNotADuplicate(): void
    {
        $document = new ApiDocument();
        $document->addEndpoint($this->endpoint('User', 'index', '/user/list', 'GET'));
        $document->addEndpoint($this->endpoint('User', 'create', '/user/list', 'POST'));

        self::assertSame([], (new DocumentValidator())->validate($document));
    }

    public function testNoticesEndpointsWithoutSummaryOrDescription(): void
    {
        $document = new ApiDocument();
        $endpoint = $this->endpoint('User', 'index', '/user/list');
        $endpoint->summary = '';
        $endpoint->description = '';
        $document->addEndpoint($endpoint);

        $diagnostics = (new DocumentValidator())->validate($document);

        self::assertCount(1, $diagnostics);
        self::assertSame('endpoint.no_summary', $diagnostics[0]->code);
        self::assertSame('notice', $diagnostics[0]->level);
    }

    public function testADescriptionWithoutASummaryIsEnough(): void
    {
        $document = new ApiDocument();
        $endpoint = $this->endpoint('User', 'index', '/user/list');
        $endpoint->summary = '';
        $endpoint->description = '只有描述';
        $document->addEndpoint($endpoint);

        self::assertSame([], (new DocumentValidator())->validate($document));
    }

    public function testIgnoredEndpointsAreSkippedEntirely(): void
    {
        $document = new ApiDocument();

        $ignored = $this->endpoint('User', 'secret', null);
        $ignored->ignored = true;
        $document->addEndpoint($ignored);

        self::assertSame([], (new DocumentValidator())->validate($document));
    }

    public function testDiagnosticsPointAtTheSource(): void
    {
        $document = new ApiDocument();
        $document->addEndpoint($this->endpoint('User', 'index', null));

        $diagnostic = (new DocumentValidator())->validate($document)[0];

        self::assertSame('/app/Api/Controller/User.php', $diagnostic->file);
        self::assertSame(42, $diagnostic->line);
    }

    private function endpoint(string $controller, string $action, ?string $route, string $method = 'GET'): ApiEndpoint
    {
        $endpoint = new ApiEndpoint(
            'App\\Api\\Controller\\' . $controller,
            $action,
            '/app/Api/Controller/' . $controller . '.php',
            42
        );

        $endpoint->summary = '摘要';
        $endpoint->route = $route;
        $endpoint->httpMethod = $method;

        return $endpoint;
    }
}
