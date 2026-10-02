<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Fixtures\Controllers\Admin;

final class RoleController
{
    /**
     * @name 角色列表
     * @route /admin/role/list
     * @method GET
     * @group 后台
     * @auth bearer
     * @response {"code":200,"data":[]}
     */
    public function index(): void
    {
    }
}
