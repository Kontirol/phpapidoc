<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Model;

/**
 * A single security scheme, emitted under components.securitySchemes.
 */
final class SecurityScheme
{
    public const TYPE_HTTP = 'http';
    public const TYPE_API_KEY = 'apiKey';
    public const TYPE_OAUTH2 = 'oauth2';
    public const TYPE_OPEN_ID_CONNECT = 'openIdConnect';

    /**
     * @var string Name referenced by "security" requirements.
     */
    public $name;

    /**
     * @var string One of the TYPE_* constants.
     */
    public $type = self::TYPE_HTTP;

    /**
     * @var string|null http scheme: bearer, basic, ...
     */
    public $scheme;

    /**
     * @var string|null
     */
    public $bearerFormat;

    /**
     * @var string|null apiKey location: header, query, cookie.
     */
    public $in;

    /**
     * @var string|null apiKey parameter name.
     */
    public $parameterName;

    /**
     * @var string
     */
    public $description = '';

    public function __construct(string $name, string $type = self::TYPE_HTTP)
    {
        $this->name = $name;
        $this->type = $type;
    }

    /**
     * Builds a scheme from the raw configuration array.
     *
     * @param array<string, mixed> $definition
     */
    public static function fromArray(string $name, array $definition): self
    {
        $type = isset($definition['type']) && is_string($definition['type'])
            ? $definition['type']
            : self::TYPE_HTTP;

        $scheme = new self($name, $type);

        if (isset($definition['description']) && is_string($definition['description'])) {
            $scheme->description = $definition['description'];
        }

        if (isset($definition['scheme']) && is_string($definition['scheme'])) {
            $scheme->scheme = $definition['scheme'];
        }

        if (isset($definition['bearerFormat']) && is_string($definition['bearerFormat'])) {
            $scheme->bearerFormat = $definition['bearerFormat'];
        }

        if (isset($definition['in']) && is_string($definition['in'])) {
            $scheme->in = $definition['in'];
        }

        // For an apiKey scheme the config key "name" holds the header or query
        // parameter name, which would otherwise clash with the scheme name.
        if (isset($definition['name']) && is_string($definition['name'])) {
            $scheme->parameterName = $definition['name'];
        }

        return $scheme;
    }

    public static function bearer(string $name = 'bearerAuth', ?string $format = null): self
    {
        $scheme = new self($name, self::TYPE_HTTP);
        $scheme->scheme = 'bearer';
        $scheme->bearerFormat = $format;

        return $scheme;
    }

    public static function apiKey(string $name, string $parameterName, string $in = 'header'): self
    {
        $scheme = new self($name, self::TYPE_API_KEY);
        $scheme->parameterName = $parameterName;
        $scheme->in = $in;

        return $scheme;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $definition = ['type' => $this->type];

        if ($this->description !== '') {
            $definition['description'] = $this->description;
        }

        if ($this->type === self::TYPE_HTTP) {
            $definition['scheme'] = $this->scheme ?? 'bearer';

            if ($this->bearerFormat !== null) {
                $definition['bearerFormat'] = $this->bearerFormat;
            }
        }

        if ($this->type === self::TYPE_API_KEY) {
            $definition['in'] = $this->in ?? 'header';
            $definition['name'] = $this->parameterName ?? 'X-Api-Key';
        }

        return $definition;
    }
}
