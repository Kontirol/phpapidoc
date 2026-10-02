<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\OpenApi;

use Kontirol\ApiDoc\Model\ApiDocument;
use Kontirol\ApiDoc\Model\ApiEndpoint;
use Kontirol\ApiDoc\Model\Parameter;
use Kontirol\ApiDoc\Model\RequestBody;
use Kontirol\ApiDoc\Model\Response;

/**
 * Renders an ApiDocument as an OpenAPI 3.0 specification.
 */
final class OpenApiBuilder
{
    public const VERSION = '3.0.3';

    /**
     * @var SchemaInferrer
     */
    private $inferrer;

    public function __construct(?SchemaInferrer $inferrer = null)
    {
        $this->inferrer = $inferrer ?? new SchemaInferrer();
    }

    /**
     * @return array<string, mixed>
     */
    public function build(ApiDocument $document): array
    {
        /** @var array<string, int> $usedIds */
        $usedIds = [];

        /** @var array<string, array<string, array<string, mixed>>> $paths */
        $paths = [];

        /** @var array<string, true> $tagNames */
        $tagNames = [];

        foreach ($document->documentedEndpoints() as $endpoint) {
            foreach ($endpoint->tags as $tag) {
                $tagNames[$tag] = true;
            }

            if (!$endpoint->hasRoute()) {
                continue;
            }

            $method = strtolower($endpoint->httpMethod === null ? 'get' : $endpoint->httpMethod);

            $paths[(string) $endpoint->route][$method] = $this->operation($endpoint, $document, $usedIds);
        }

        foreach (array_keys($document->tags) as $tag) {
            $tagNames[$tag] = true;
        }

        ksort($paths);

        foreach ($paths as $path => $operations) {
            ksort($operations);
            $paths[$path] = $operations;
        }

        $spec = [
            'openapi' => self::VERSION,
            'info' => $document->info,
        ];

        if ($document->servers !== []) {
            $spec['servers'] = array_map(static function (string $url): array {
                return ['url' => $url];
            }, $document->servers);
        }

        $tags = $this->tagList(array_keys($tagNames), $document->tags);

        if ($tags !== []) {
            $spec['tags'] = $tags;
        }

        $spec['paths'] = $paths;

        $components = $this->components($document);

        if ($components !== []) {
            $spec['components'] = $components;
        }

        return $spec;
    }

    /**
     * @param array<string, int> $usedIds
     *
     * @return array<string, mixed>
     */
    private function operation(ApiEndpoint $endpoint, ApiDocument $document, array &$usedIds): array
    {
        $operation = [];

        if ($endpoint->summary !== '') {
            $operation['summary'] = $endpoint->summary;
        }

        if ($endpoint->description !== '') {
            $operation['description'] = $endpoint->description;
        }

        $operation['operationId'] = $this->operationId($endpoint, $usedIds);

        if ($endpoint->tags !== []) {
            $operation['tags'] = array_values($endpoint->tags);
        }

        if ($endpoint->deprecated) {
            $operation['deprecated'] = true;
        }

        $parameters = $this->parameters($endpoint);

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        if ($endpoint->requestBody instanceof RequestBody) {
            $operation['requestBody'] = $this->requestBody($endpoint->requestBody);
        }

        $operation['responses'] = $this->responses($endpoint);

        $security = $this->security($endpoint, $document);

        if ($security !== null) {
            $operation['security'] = $security;
        }

        return $operation;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parameters(ApiEndpoint $endpoint): array
    {
        $parameters = [];

        foreach ($endpoint->parameters as $parameter) {
            $entry = [
                'name' => $parameter->name,
                'in' => $parameter->in,
            ];

            if ($parameter->required || $parameter->isInPath()) {
                $entry['required'] = true;
            }

            if ($parameter->description !== '') {
                $entry['description'] = $parameter->description;
            }

            if ($parameter->deprecated) {
                $entry['deprecated'] = true;
            }

            $entry['schema'] = $this->parameterSchema($parameter);

            if ($parameter->hasExample) {
                $entry['example'] = $parameter->example;
            }

            $parameters[] = $entry;
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function parameterSchema(Parameter $parameter): array
    {
        $schema = ['type' => $parameter->type];

        if ($parameter->hasDefault) {
            $schema['default'] = $parameter->default;
        }

        if ($parameter->enum !== null && $parameter->enum !== []) {
            $schema['enum'] = $parameter->enum;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestBody(RequestBody $body): array
    {
        $result = [
            'required' => $body->required,
            'content' => [$body->contentType() => $this->bodyContent($body)],
        ];

        if ($body->description !== '') {
            $result['description'] = $body->description;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyContent(RequestBody $body): array
    {
        if ($body->hasProperties()) {
            return $this->propertyBody($body);
        }

        if ($body->hasExample) {
            return [
                'schema' => $this->inferrer->infer($body->example),
                'example' => $body->example,
            ];
        }

        return ['schema' => ['type' => 'object']];
    }

    /**
     * @return array<string, mixed>
     */
    private function propertyBody(RequestBody $body): array
    {
        $properties = [];
        $required = [];

        foreach ($body->properties as $property) {
            $schema = ['type' => $property->type];

            if ($property->description !== '') {
                $schema['description'] = $property->description;
            }

            if ($property->hasDefault) {
                $schema['default'] = $property->default;
            }

            $properties[$property->name] = $schema;

            if ($property->required) {
                $required[] = $property->name;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        if ($body->hasExample) {
            return ['schema' => $schema, 'example' => $body->example];
        }

        return ['schema' => $schema];
    }

    /**
     * @return array<string, mixed>
     */
    private function responses(ApiEndpoint $endpoint): array
    {
        $responses = [];

        foreach ($endpoint->responses as $response) {
            $responses[$response->statusCode] = $this->response($response);
        }

        if ($responses === []) {
            $responses['200'] = ['description' => '成功'];
        }

        ksort($responses);

        return $responses;
    }

    /**
     * @return array<string, mixed>
     */
    private function response(Response $response): array
    {
        $entry = ['description' => $response->description === '' ? '响应' : $response->description];

        if ($response->hasExample) {
            $entry['content'] = [
                $response->contentType => [
                    'schema' => $this->inferrer->infer($response->example),
                    'example' => $response->example,
                ],
            ];
        }

        return $entry;
    }

    /**
     * Returns null when the operation inherits the document default, [] when it
     * is explicitly public, or the requirement map otherwise.
     *
     * @return array<string, list<string>>|null
     */
    private function security(ApiEndpoint $endpoint, ApiDocument $document): ?array
    {
        if ($endpoint->auth === null || $endpoint->auth === '') {
            return $document->defaultSecurity === [] ? null : $document->defaultSecurity;
        }

        $auth = strtolower(trim($endpoint->auth));

        if (in_array($auth, ['none', 'public', 'no', 'false', '0', '-'], true)) {
            return [];
        }

        if (in_array($auth, ['bearer', 'token', 'jwt'], true)) {
            return ['bearerAuth' => []];
        }

        if (in_array($auth, ['apikey', 'api_key', 'key'], true)) {
            return ['apiKey' => []];
        }

        return [$endpoint->auth => []];
    }

    /**
     * @param array<string, int> $usedIds
     */
    private function operationId(ApiEndpoint $endpoint, array &$usedIds): string
    {
        $base = $endpoint->shortController() . '.' . $endpoint->action;

        if (!isset($usedIds[$base])) {
            $usedIds[$base] = 1;

            return $base;
        }

        $usedIds[$base]++;

        return $base . '_' . $usedIds[$base];
    }

    /**
     * @param list<string>          $names
     * @param array<string, string> $descriptions
     *
     * @return list<array<string, string>>
     */
    private function tagList(array $names, array $descriptions): array
    {
        $tags = [];

        foreach ($names as $name) {
            $entry = ['name' => $name];

            if (isset($descriptions[$name]) && $descriptions[$name] !== '') {
                $entry['description'] = $descriptions[$name];
            }

            $tags[] = $entry;
        }

        return $tags;
    }

    /**
     * @return array<string, mixed>
     */
    private function components(ApiDocument $document): array
    {
        $schemes = [];

        foreach ($document->securitySchemes as $name => $scheme) {
            $schemes[$name] = $scheme->toArray();
        }

        if ($schemes === [] && $this->usesAuthentication($document)) {
            $schemes['bearerAuth'] = ['type' => 'http', 'scheme' => 'bearer'];
        }

        if ($schemes === []) {
            return [];
        }

        return ['securitySchemes' => $schemes];
    }

    private function usesAuthentication(ApiDocument $document): bool
    {
        foreach ($document->documentedEndpoints() as $endpoint) {
            if ($endpoint->auth !== null && $endpoint->auth !== '') {
                return true;
            }
        }

        return false;
    }
}
