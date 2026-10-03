<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Route;

/**
 * Implemented by route sources that can also report the URL conventions of the
 * framework they introspected.
 *
 * A controller class name alone does not determine its URL. The same
 * "OrderController" is reached at "/api/ordercontroller" when the framework
 * keeps the suffix in place and at "/api/order" when it strips it, and which one
 * applies depends on application configuration rather than on the framework
 * version. Only the framework itself can answer that, so the source that has
 * already bootstrapped it is the natural place to ask.
 *
 * Implementations must never throw: an unknown convention is reported as null so
 * the caller can fall back to its own configuration.
 */
interface FrameworkConventionsInterface
{
    /**
     * The conventions to spell URLs with.
     *
     * @return array{controllerSuffix: ?bool, urlCase: ?string}
     *   controllerSuffix: true when "app\api\controller\OrderController" is
     *     reached at "/api/order", false when it stays at "/api/ordercontroller",
     *     null when the framework could not be asked.
     *   urlCase: one of the UrlInferrer::CASE_* constants, or null when unknown.
     */
    public function urlConventions(): array;
}
