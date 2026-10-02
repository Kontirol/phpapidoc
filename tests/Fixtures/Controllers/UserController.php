<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Fixtures\Controllers;

/**
 * Class level docblock: must never be turned into an endpoint.
 */
final class UserController
{
    /**
     * @name 用户列表
     * @desc 分页返回用户列表
     * @route /user/list
     * @method GET
     * @group 用户
     * @param int page 页码，默认1
     * @param int pageSize 每页数量，默认10
     * @response {"code":200,"data":[{"id":1,"name":"张三"},{"id":2,"name":"李四"}]}
     */
    public function index(): void
    {
    }

    /**
     * @name 用户详情
     * @desc 按 ID 返回用户
     * @route /user/detail
     * @method GET
     * @group 用户
     * @auth bearer
     * @param int id 用户 ID
     * @response {"code":200,"data":{"id":1,"name":"张三"}}
     */
    public function detail(): void
    {
    }

    /**
     * @name 创建用户
     * @route /user/create
     * @method POST
     * @body {"name":"张三","age":18}
     * @response 201 {"code":200,"data":{"id":3}}
     * @response 400 {"code":400,"message":"参数错误"}
     */
    public function create(): void
    {
    }

    /**
     * @name 内部方法
     * @ignore
     */
    public function internal(): void
    {
    }

    public function notDocumented(): void
    {
    }
}
