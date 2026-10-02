<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Validator;

use Kontirol\ApiDoc\Model\ApiDocument;
use Kontirol\ApiDoc\Model\ApiEndpoint;
use Kontirol\ApiDoc\Model\Diagnostic;

/**
 * Structural checks that do not depend on the OpenAPI renderer.
 */
final class DocumentValidator
{
    /**
     * @return list<Diagnostic>
     */
    public function validate(ApiDocument $document): array
    {
        $diagnostics = [];
        $seen = [];

        foreach ($document->documentedEndpoints() as $endpoint) {
            if (!$endpoint->hasRoute()) {
                $diagnostics[] = Diagnostic::warning(
                    'endpoint.no_route',
                    sprintf(
                        '%s::%s() has no usable route; add @route or configure a route source.',
                        $endpoint->shortController(),
                        $endpoint->action
                    ),
                    $endpoint->file,
                    $endpoint->line
                );

                continue;
            }

            $key = $this->routeKey($endpoint);

            if (isset($seen[$key])) {
                $diagnostics[] = Diagnostic::error(
                    'endpoint.duplicate_route',
                    sprintf(
                        'Duplicate operation %s: also declared by %s::%s().',
                        $key,
                        $seen[$key]->shortController(),
                        $seen[$key]->action
                    ),
                    $endpoint->file,
                    $endpoint->line
                );

                continue;
            }

            $seen[$key] = $endpoint;

            if ($endpoint->summary === '' && $endpoint->description === '') {
                $diagnostics[] = Diagnostic::notice(
                    'endpoint.no_summary',
                    sprintf(
                        '%s::%s() has neither @name nor @desc, the operation will have no summary.',
                        $endpoint->shortController(),
                        $endpoint->action
                    ),
                    $endpoint->file,
                    $endpoint->line
                );
            }
        }

        return $diagnostics;
    }

    private function routeKey(ApiEndpoint $endpoint): string
    {
        $method = $endpoint->httpMethod === null || $endpoint->httpMethod === ''
            ? 'GET'
            : strtoupper($endpoint->httpMethod);

        return $method . ' ' . (string) $endpoint->route;
    }
}
