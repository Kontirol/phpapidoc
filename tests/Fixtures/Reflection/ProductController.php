<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Fixtures\Reflection;

use stdClass;

/**
 * Fixture for the reflection enricher: the docblocks deliberately document only
 * some of the method arguments.
 */
final class ProductController
{
    /**
     * @name 商品列表
     * @route /product/list
     * @method GET
     * @param int categoryId 分类 ID
     */
    public function index(int $page = 1, string $keyword = '', int $categoryId = 0): void
    {
    }

    /**
     * @name 商品详情
     * @route /product/detail
     * @method GET
     * @param int id 商品 ID
     */
    public function detail(int $id): void
    {
    }

    /**
     * @name 上传商品
     * @route /product/upload
     * @method POST
     * @param string name 商品名称
     */
    public function upload(stdClass $payload, string $name, bool $draft): void
    {
    }

    /**
     * @name 未类型化的参数
     * @route /product/legacy
     * @method GET
     */
    public function legacy($anything): void
    {
    }

    public function notDocumented(int $whatever): void
    {
    }
}
