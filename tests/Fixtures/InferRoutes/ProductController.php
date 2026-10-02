<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Fixtures\InferRoutes;

/**
 * Fixture for URL inference and the "include undocumented" mode.
 *
 * The controller sits in a namespace that does not follow the ThinkPHP
 * convention, so the tests configure an explicit url prefix instead.
 */
final class ProductController
{
    /**
     * @name 商品列表
     */
    public function index(): array
    {
        return [];
    }

    /**
     * @name 商品详情
     * @route /custom/product/detail
     * @method GET
     */
    public function detail(): array
    {
        return [];
    }

    /**
     * 有注释，但没有任何 apidoc 标签。
     */
    public function undocumentedWithDocBlock(): array
    {
        return [];
    }

    public function notDocumentedAtAll(): array
    {
        return [];
    }

    public function save(): array
    {
        return [];
    }

    public function remove(): array
    {
        return [];
    }
}
