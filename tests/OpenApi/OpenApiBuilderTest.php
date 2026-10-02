<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\OpenApi;

use Kontirol\ApiDoc\Model\ApiDocument;
use Kontirol\ApiDoc\Model\ApiEndpoint;
use Kontirol\ApiDoc\Model\Parameter;
use Kontirol\ApiDoc\Model\RequestBody;
use Kontirol\ApiDoc\Model\Response;
use Kontirol\ApiDoc\Model\SecurityScheme;
use Kontirol\ApiDoc\OpenApi\OpenApiBuilder;
use PHPUnit\Framework\TestCase;

final class OpenApiBuilderTest extends TestCase
{
    public function testBuildsMinimalSpecification(): void
    {
        $document = new ApiDocument(['title' => 'Demo API', 'version' => '2.0.0']);

        $spec = (new OpenApiBuilder())->build($document);

        self::assertSame('3.0.3', $spec['openapi']);
        self::assertSame('Demo API', $spec['info']['title']);
        self::assertSame('2.0.0', $spec['info']['version']);
        self::assertSame([], $spec['paths']);
    }

    public function testRendersAnOperation(): void
    {
        $document = new ApiDocument(['title' => 'Demo']);
        $document->addEndpoint($this->userListEndpoint());

        $spec = (new OpenApiBuilder())->build($document);

        self::assertArrayHasKey('/user/list', $spec['paths']);
        self::assertArrayHasKey('get', $spec['paths']['/user/list']);

        $operation = $spec['paths']['/user/list']['get'];

        self::assertSame('用户列表', $operation['summary']);
        self::assertSame('User.index', $operation['operationId']);
        self::assertSame(['用户'], $operation['tags']);
        self::assertCount(1, $operation['parameters']);
        self::assertArrayHasKey('200', $operation['responses']);
        // PHP turns the numeric status code into an integer key.
        self::assertSame([200], array_keys($operation['responses']));
    }

    public function testParameterSchemaCarriesTypeDefaultAndExample(): void
    {
        $document = new ApiDocument();
        $endpoint = $this->userListEndpoint();
        $document->addEndpoint($endpoint);

        $parameter = $endpoint->parameters[0];
        $parameter->hasDefault = true;
        $parameter->default = 1;
        $parameter->hasExample = true;
        $parameter->example = 2;

        $spec = (new OpenApiBuilder())->build($document);
        $rendered = $spec['paths']['/user/list']['get']['parameters'][0];

        self::assertSame('page', $rendered['name']);
        self::assertSame('query', $rendered['in']);
        self::assertSame('integer', $rendered['schema']['type']);
        self::assertSame(1, $rendered['schema']['default']);
        self::assertSame(2, $rendered['example']);
        self::assertArrayNotHasKey('required', $rendered);
    }

    public function testPathParametersAreAlwaysRequired(): void
    {
        $document = new ApiDocument();
        $endpoint = new ApiEndpoint('App\\Controller\\Order', 'detail');
        $endpoint->route = '/order/{id}';
        $endpoint->httpMethod = 'GET';

        $id = new Parameter('id', 'string', '订单 ID');
        $id->in = Parameter::IN_PATH;
        $id->required = false;
        $endpoint->addParameter($id);

        $document->addEndpoint($endpoint);

        $spec = (new OpenApiBuilder())->build($document);

        self::assertTrue($spec['paths']['/order/{id}']['get']['parameters'][0]['required']);
    }

    public function testResponseExampleBecomesASchema(): void
    {
        $document = new ApiDocument();
        $endpoint = new ApiEndpoint('App\\Controller\\User', 'index');
        $endpoint->route = '/user/list';
        $endpoint->httpMethod = 'GET';

        $response = new Response('200', '成功');
        $response->example = ['code' => 200, 'data' => []];
        $response->hasExample = true;
        $endpoint->addResponse($response);
        $document->addEndpoint($endpoint);

        $spec = (new OpenApiBuilder())->build($document);
        $rendered = $spec['paths']['/user/list']['get']['responses']['200'];

        self::assertSame('成功', $rendered['description']);
        self::assertSame('object', $rendered['content']['application/json']['schema']['type']);
        self::assertSame(200, $rendered['content']['application/json']['example']['code']);
    }

    public function testRequestBodyIsRendered(): void
    {
        $document = new ApiDocument();
        $endpoint = new ApiEndpoint('App\\Controller\\User', 'create');
        $endpoint->route = '/user/create';
        $endpoint->httpMethod = 'POST';

        $body = new RequestBody();
        $body->required = true;
        $body->example = ['name' => '张三', 'age' => 18];
        $body->hasExample = true;
        $endpoint->requestBody = $body;
        $document->addEndpoint($endpoint);

        $spec = (new OpenApiBuilder())->build($document);
        $rendered = $spec['paths']['/user/create']['post']['requestBody'];

        self::assertTrue($rendered['required']);
        self::assertArrayHasKey('application/json', $rendered['content']);
        self::assertSame('object', $rendered['content']['application/json']['schema']['type']);
    }

    public function testFormRequestBodyUsesTheRightContentType(): void
    {
        $document = new ApiDocument();
        $endpoint = new ApiEndpoint('App\\Controller\\Upload', 'image');
        $endpoint->route = '/upload/image';
        $endpoint->httpMethod = 'POST';

        $body = new RequestBody();
        $body->type = RequestBody::TYPE_MULTIPART;
        $endpoint->requestBody = $body;
        $document->addEndpoint($endpoint);

        $spec = (new OpenApiBuilder())->build($document);

        self::assertArrayHasKey(
            'multipart/form-data',
            $spec['paths']['/upload/image']['post']['requestBody']['content']
        );
    }

    public function testAuthTagBecomesASecurityRequirement(): void
    {
        $document = new ApiDocument();
        $endpoint = $this->userListEndpoint();
        $endpoint->auth = 'bearer';
        $document->addEndpoint($endpoint);

        $spec = (new OpenApiBuilder())->build($document);

        self::assertSame(['bearerAuth' => []], $spec['paths']['/user/list']['get']['security']);
        self::assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
    }

    public function testPublicEndpointOverridesTheDocumentDefault(): void
    {
        $document = new ApiDocument();
        $document->defaultSecurity = ['bearerAuth' => []];

        $endpoint = $this->userListEndpoint();
        $endpoint->auth = 'none';
        $document->addEndpoint($endpoint);

        $spec = (new OpenApiBuilder())->build($document);

        self::assertSame([], $spec['paths']['/user/list']['get']['security']);
    }

    public function testConfiguredSecuritySchemeIsUsed(): void
    {
        $document = new ApiDocument();
        $document->securitySchemes['bearerAuth'] = SecurityScheme::bearer('bearerAuth', 'JWT');

        $endpoint = $this->userListEndpoint();
        $endpoint->auth = 'bearer';
        $document->addEndpoint($endpoint);

        $spec = (new OpenApiBuilder())->build($document);

        self::assertSame('JWT', $spec['components']['securitySchemes']['bearerAuth']['bearerFormat']);
    }

    public function testIgnoredEndpointsAreNotRendered(): void
    {
        $document = new ApiDocument();
        $endpoint = $this->userListEndpoint();
        $endpoint->ignored = true;
        $document->addEndpoint($endpoint);

        $spec = (new OpenApiBuilder())->build($document);

        self::assertSame([], $spec['paths']);
    }

    public function testEndpointsWithoutRouteAreSkipped(): void
    {
        $document = new ApiDocument();
        $endpoint = new ApiEndpoint('App\\Controller\\User', 'index');
        $endpoint->summary = '无路径';
        $document->addEndpoint($endpoint);

        $spec = (new OpenApiBuilder())->build($document);

        self::assertSame([], $spec['paths']);
    }

    public function testTagsAndServersAreRendered(): void
    {
        $document = new ApiDocument(['title' => 'Demo'], ['http://localhost:8000']);
        $document->tags = ['用户' => '用户中心'];
        $document->addEndpoint($this->userListEndpoint());

        $spec = (new OpenApiBuilder())->build($document);

        self::assertSame([['url' => 'http://localhost:8000']], $spec['servers']);
        self::assertSame([['name' => '用户', 'description' => '用户中心']], $spec['tags']);
    }

    public function testOperationsAreSortedForStableOutput(): void
    {
        $document = new ApiDocument();

        $second = new ApiEndpoint('App\\Controller\\B', 'index');
        $second->route = '/b';
        $second->httpMethod = 'GET';
        $document->addEndpoint($second);

        $first = new ApiEndpoint('App\\Controller\\A', 'index');
        $first->route = '/a';
        $first->httpMethod = 'POST';
        $document->addEndpoint($first);

        $spec = (new OpenApiBuilder())->build($document);

        self::assertSame(['/a', '/b'], array_keys($spec['paths']));
        self::assertSame(['post'], array_keys($spec['paths']['/a']));
    }

    public function testResponsesSerializeAsAnObject(): void
    {
        $document = new ApiDocument();
        $endpoint = $this->userListEndpoint();
        $endpoint->addResponse(new Response('404', '资源不存在'));
        $document->addEndpoint($endpoint);

        $json = (string) json_encode((new OpenApiBuilder())->build($document));

        self::assertStringContainsString('"200":', $json);
        self::assertStringContainsString('"404":', $json);
    }

    private function userListEndpoint(): ApiEndpoint
    {
        $endpoint = new ApiEndpoint('App\\Controller\\User', 'index', 'file.php', 10);
        $endpoint->summary = '用户列表';
        $endpoint->route = '/user/list';
        $endpoint->httpMethod = 'GET';
        $endpoint->addTag('用户');
        $endpoint->addParameter(new Parameter('page', 'integer', '页码'));
        $endpoint->addResponse(new Response('200', '成功'));

        return $endpoint;
    }
}
