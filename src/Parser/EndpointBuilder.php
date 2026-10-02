<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Parser;

use Kontirol\ApiDoc\Model\ApiEndpoint;
use Kontirol\ApiDoc\Model\Diagnostic;
use Kontirol\ApiDoc\Model\Parameter;
use Kontirol\ApiDoc\Model\RequestBody;
use Kontirol\ApiDoc\Model\Response;

/**
 * Builds an ApiEndpoint out of a method docblock.
 *
 * This is where the annotation DSL gains meaning. Returning null means "this
 * method is not part of the API documentation at all".
 */
final class EndpointBuilder
{
    /**
     * @var list<string>
     */
    private const BODY_TYPES = ['json', 'form', 'multipart', 'text'];

    /**
     * @var DocBlockParser
     */
    private $docBlockParser;

    /**
     * @var ParameterParser
     */
    private $parameterParser;

    /**
     * @var ResponseParser
     */
    private $responseParser;

    /**
     * @var list<Diagnostic>
     */
    private $diagnostics = [];

    /**
     * @var bool
     */
    private $includeUndocumented = false;

    public function __construct(
        ?DocBlockParser $docBlockParser = null,
        ?ParameterParser $parameterParser = null,
        ?ResponseParser $responseParser = null,
        bool $includeUndocumented = false
    ) {
        $this->docBlockParser = $docBlockParser ?? new DocBlockParser();
        $this->parameterParser = $parameterParser ?? new ParameterParser();
        $this->responseParser = $responseParser ?? new ResponseParser();
        $this->includeUndocumented = $includeUndocumented;
    }

    /**
     * Everything noticed since the builder was created.
     *
     * @return list<Diagnostic>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function build(string $fqcn, ParsedMethod $method, ?string $file = null): ?ApiEndpoint
    {
        if (!$method->hasDocBlock()) {
            return $this->buildUndocumented($fqcn, $method, $file);
        }

        $tags = $this->docBlockParser->parse((string) $method->docBlock, $method->line);

        if (!$this->looksLikeAnEndpoint($tags)) {
            return $this->buildUndocumented($fqcn, $method, $file);
        }

        $endpoint = new ApiEndpoint($fqcn, $method->name, $file, $method->line);

        if ($tags->has(TagName::IGNORE)) {
            $endpoint->ignored = true;

            return $endpoint;
        }

        $this->applyScalarTags($endpoint, $tags);
        $this->applyParameters($endpoint, $tags, $file);
        $this->applyBody($endpoint, $tags, $file);
        $this->applyResponses($endpoint, $tags, $file);
        $this->detectPathParameters($endpoint);

        if ($endpoint->responses === []) {
            $endpoint->addResponse(new Response('200', '成功'));
        }

        // Plenty of real world docblocks are plain PHPDoc: they carry @param but
        // never say @method. Guessing from the action name beats silently
        // claiming every such endpoint is a GET.
        if ($endpoint->httpMethod === null || $endpoint->httpMethod === '') {
            $guessed = self::guessHttpMethod($endpoint->action);

            $endpoint->httpMethod = $guessed;

            $this->diagnostics[] = Diagnostic::notice(
                'method.guessed',
                sprintf(
                    '%s::%s() has no @method, %s was guessed from its name.',
                    $endpoint->shortController(),
                    $endpoint->action,
                    $guessed
                ),
                $endpoint->file,
                $endpoint->line
            );
        }

        return $endpoint;
    }

    /**
     * Used by the "include undocumented" mode: every public method becomes an
     * operation, with the HTTP verb guessed from its name.
     */
    private function buildUndocumented(string $fqcn, ParsedMethod $method, ?string $file): ?ApiEndpoint
    {
        if (!$this->includeUndocumented || strpos($method->name, '__') === 0) {
            return null;
        }

        $endpoint = new ApiEndpoint($fqcn, $method->name, $file, $method->line);
        $endpoint->summary = $method->name;

        $verb = self::guessHttpMethod($method->name);

        $endpoint->httpMethod = $verb;
        $endpoint->addResponse(new Response('200', '成功'));

        $this->diagnostics[] = Diagnostic::notice(
            'endpoint.undocumented',
            sprintf(
                '%s::%s() carries no apidoc tag, it was documented with a guessed %s method.',
                $endpoint->shortController(),
                $method->name,
                $verb
            ),
            $file,
            $method->line
        );

        return $endpoint;
    }

    /**
     * Verbs that turn an action into a write operation, matched on whole words
     * so that "save_word" and "remove_word" are recognised while "userinfo" is
     * not. Everything else stays GET; the guess is always reported as a notice,
     * so it is never silently wrong.
     */
    private const WRITE_WORDS = [
        'create', 'store', 'save', 'add', 'insert', 'submit', 'send', 'post',
        'report', 'upload', 'login', 'logout', 'refresh', 'register', 'bind',
        'unbind', 'publish', 'approve', 'reject', 'pay', 'payment', 'start',
        'stop', 'lock', 'unlock', 'close', 'notify', 'callback',
    ];

    /**
     * @var list<string>
     */
    private const UPDATE_WORDS = ['update', 'edit', 'modify', 'put', 'patch'];

    /**
     * @var list<string>
     */
    private const DELETE_WORDS = ['delete', 'destroy', 'remove', 'clear', 'cancel', 'drop', 'revoke'];

    private static function guessHttpMethod(string $action): string
    {
        // "sendCode" must split like "send_code" does; lower casing first would
        // glue the words together and lose the hint entirely.
        $snake = (string) preg_replace('#(?<!^)[A-Z]#', '_$0', $action);

        $words = preg_split('#[^a-zA-Z0-9]+#', strtolower($snake));

        if ($words === false || $words === []) {
            return 'GET';
        }

        if (array_intersect($words, self::DELETE_WORDS) !== []) {
            return 'DELETE';
        }

        if (array_intersect($words, self::UPDATE_WORDS) !== []) {
            return 'PUT';
        }

        if (array_intersect($words, self::WRITE_WORDS) !== []) {
            return 'POST';
        }

        return 'GET';
    }

    /**
     * A docblock only describes an endpoint when it carries at least one tag
     * that is meaningful for the documentation.
     */
    private function looksLikeAnEndpoint(ParsedDocBlock $tags): bool
    {
        $meaningful = [
            TagName::NAME,
            TagName::ROUTE,
            TagName::METHOD,
            TagName::GROUP,
            TagName::TAG,
            TagName::PARAM,
            TagName::RESPONSE,
            TagName::BODY,
            TagName::BODY_PARAM,
            TagName::IGNORE,
        ];

        foreach ($meaningful as $name) {
            if ($tags->has($name)) {
                return true;
            }
        }

        return false;
    }

    private function applyScalarTags(ApiEndpoint $endpoint, ParsedDocBlock $tags): void
    {
        $endpoint->summary = $tags->value(TagName::NAME);
        $endpoint->description = $tags->value(TagName::DESC);

        if ($tags->has(TagName::ROUTE)) {
            $endpoint->route = self::normalizeRoute($tags->value(TagName::ROUTE));
        }

        if ($tags->has(TagName::METHOD)) {
            $endpoint->httpMethod = strtoupper(trim($tags->value(TagName::METHOD)));
        }

        foreach ($tags->all(TagName::GROUP) as $tag) {
            $endpoint->addTag(trim($tag->value));
        }

        // @tag is an alias of @group, it reads better in a docblock.
        foreach ($tags->all(TagName::TAG) as $tag) {
            $endpoint->addTag(trim($tag->value));
        }

        if ($tags->has(TagName::AUTH)) {
            $endpoint->auth = strtolower(trim($tags->value(TagName::AUTH)));
        }

        $endpoint->deprecated = $tags->has(TagName::DEPRECATED);
    }

    private function applyParameters(ApiEndpoint $endpoint, ParsedDocBlock $tags, ?string $file): void
    {
        foreach ($tags->all(TagName::PARAM) as $tag) {
            $parameter = $this->parameterParser->parse($tag, $file, $this->diagnostics);

            if ($parameter !== null) {
                $endpoint->addParameter($parameter);
            }
        }
    }

    private function applyBody(ApiEndpoint $endpoint, ParsedDocBlock $tags, ?string $file): void
    {
        $bodyTag = $tags->first(TagName::BODY);
        $bodyParams = $tags->all(TagName::BODY_PARAM);

        if ($bodyTag === null && $bodyParams === []) {
            return;
        }

        $body = new RequestBody();

        if ($bodyTag !== null) {
            $this->interpretBody($body, $bodyTag, $file);
        }

        foreach ($bodyParams as $tag) {
            $parameter = $this->parameterParser->parse($tag, $file, $this->diagnostics);

            if ($parameter !== null) {
                $body->addProperty($parameter);
            }
        }

        // A documented request body is part of the contract, so it is required
        // unless the docblock says otherwise.
        $body->required = true;

        $endpoint->requestBody = $body;
    }

    private function interpretBody(RequestBody $body, TagValue $tag, ?string $file): void
    {
        $value = trim($tag->value);

        if ($value === '') {
            return;
        }

        $lower = strtolower($value);

        if (in_array($lower, self::BODY_TYPES, true)) {
            $body->type = $lower;

            return;
        }

        if (preg_match('#^(json|form|multipart|text)\s+(.+)$#s', $value, $matches) === 1) {
            $body->type = $matches[1];
            $value = trim($matches[2]);
        }

        if ($body->type === RequestBody::TYPE_FORM || $body->type === RequestBody::TYPE_MULTIPART) {
            $this->applyFormPayload($body, $value);

            return;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->diagnostics[] = Diagnostic::warning(
                'body.invalid_json',
                'Invalid JSON in @body: ' . json_last_error_msg(),
                $file,
                $tag->line
            );

            return;
        }

        $body->example = $decoded;
        $body->hasExample = true;
    }

    /**
     * @param string $value Space separated "key=value" pairs.
     */
    private function applyFormPayload(RequestBody $body, string $value): void
    {
        $parts = preg_split('/\s+/', trim($value));

        if ($parts === false || $parts === []) {
            return;
        }

        $payload = [];

        foreach ($parts as $pair) {
            $position = strpos($pair, '=');

            if ($position === false) {
                continue;
            }

            $payload[substr($pair, 0, $position)] = substr($pair, $position + 1);
        }

        if ($payload !== []) {
            $body->example = $payload;
            $body->hasExample = true;
        }
    }

    private function applyResponses(ApiEndpoint $endpoint, ParsedDocBlock $tags, ?string $file): void
    {
        /** @var array<string, Response> $byStatus */
        $byStatus = [];

        foreach ($tags->all(TagName::RESPONSE) as $tag) {
            $response = $this->responseParser->parse($tag, $file, $this->diagnostics);

            if (isset($byStatus[$response->statusCode])) {
                $this->diagnostics[] = Diagnostic::notice(
                    'response.duplicate',
                    sprintf('Duplicate @response for status %s, the last one wins.', $response->statusCode),
                    $file,
                    $tag->line
                );
            }

            $byStatus[$response->statusCode] = $response;
        }

        foreach ($byStatus as $response) {
            $endpoint->addResponse($response);
        }
    }

    /**
     * Marks every @param whose name appears as {placeholder} in the route as a
     * path parameter, and documents route variables that have no @param.
     */
    public function detectPathParameters(ApiEndpoint $endpoint): void
    {
        if (!$endpoint->hasRoute()) {
            return;
        }

        $matched = preg_match_all('#\{([A-Za-z0-9_]+)\}#', (string) $endpoint->route, $matches);

        if ($matched === false || $matched === 0) {
            return;
        }

        foreach ($matches[1] as $name) {
            $parameter = $endpoint->getParameter($name);

            if ($parameter !== null) {
                $parameter->in = Parameter::IN_PATH;
                $parameter->required = true;

                continue;
            }

            $auto = new Parameter($name, 'string', '路径参数');
            $auto->in = Parameter::IN_PATH;
            $auto->required = true;
            $endpoint->addParameter($auto);

            $this->diagnostics[] = Diagnostic::notice(
                'param.auto_path',
                sprintf('Route parameter {%s} has no matching @param, a string parameter was generated.', $name),
                $endpoint->file,
                $endpoint->line
            );
        }
    }

    private static function normalizeRoute(string $route): string
    {
        $route = trim($route);

        if ($route === '') {
            return '/';
        }

        if ($route[0] !== '/') {
            $route = '/' . $route;
        }

        return $route;
    }
}
