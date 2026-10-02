<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Model;

/**
 * One documented HTTP endpoint, i.e. one public controller action.
 */
final class ApiEndpoint
{
    /**
     * @var string Fully qualified controller class name.
     */
    public $controller;

    /**
     * @var string Controller method name.
     */
    public $action;

    /**
     * @var string|null Absolute path of the file the endpoint was read from.
     */
    public $file;

    /**
     * @var int|null Line number of the docblock.
     */
    public $line;

    /**
     * @var string Short summary, from @name.
     */
    public $summary = '';

    /**
     * @var string Longer description, from @desc.
     */
    public $description = '';

    /**
     * @var string|null Route pattern, e.g. "/order/detail".
     */
    public $route;

    /**
     * @var string|null Upper case HTTP verb, e.g. "GET".
     */
    public $httpMethod;

    /**
     * @var list<string> Group names, from @group.
     */
    public $tags = [];

    /**
     * @var bool
     */
    public $deprecated = false;

    /**
     * @var bool Set by @ignore; such endpoints are skipped when writing output.
     */
    public $ignored = false;

    /**
     * @var string|null "none", or the name of a security scheme.
     */
    public $auth;

    /**
     * @var list<Parameter>
     */
    public $parameters = [];

    /**
     * @var RequestBody|null
     */
    public $requestBody;

    /**
     * @var list<Response>
     */
    public $responses = [];

    /**
     * @var array<string, mixed> Free-form extras, reserved for future tags.
     */
    public $extra = [];

    public function __construct(string $controller, string $action, ?string $file = null, ?int $line = null)
    {
        $this->controller = $controller;
        $this->action = $action;
        $this->file = $file;
        $this->line = $line;
    }

    public function id(): string
    {
        return $this->controller . '::' . $this->action;
    }

    public function shortController(): string
    {
        $position = strrpos($this->controller, '\\');

        return $position === false ? $this->controller : substr($this->controller, $position + 1);
    }

    public function isDocumented(): bool
    {
        return $this->summary !== '' || $this->description !== '' || $this->hasRoute();
    }

    public function hasRoute(): bool
    {
        return $this->route !== null && $this->route !== '';
    }

    public function addParameter(Parameter $parameter): void
    {
        $this->parameters[] = $parameter;
    }

    public function hasParameter(string $name): bool
    {
        return $this->getParameter($name) !== null;
    }

    public function getParameter(string $name): ?Parameter
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->name === $name) {
                return $parameter;
            }
        }

        return null;
    }

    public function addResponse(Response $response): void
    {
        $this->responses[] = $response;
    }

    public function hasBody(): bool
    {
        return $this->requestBody !== null;
    }

    public function addTag(string $tag): void
    {
        $tag = trim($tag);

        if ($tag !== '' && !in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }
    }

    public function primaryTag(): ?string
    {
        return $this->tags[0] ?? null;
    }
}
