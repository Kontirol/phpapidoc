<?php

declare(strict_types=1);

namespace App\Api\Controller;

/**
 * 用户接口示例。
 *
 * 每个 public 方法只要有 apidoc 认识的标签就会被收集，没有注释的方法会被忽略。
 */
class UserController
{
    /**
     * 分页返回用户列表。
     *
     * @name 用户列表
     * @route /api/user/list
     * @method GET
     * @tag 用户
     * @auth bearer
     * @param integer page=1 页码
     * @param integer size=10 每页数量
     * @param string keyword 昵称关键字
     * @response 200 {"code":200,"message":"ok","data":{"total":42,"list":[{"id":1,"nickname":"张三","school":"广东南华工商职业学院"}]}}
     */
    public function index(): array
    {
        return [];
    }

    /**
     * @name 用户详情
     * @desc 按 ID 返回单个用户。
     * @route /api/user/{id}
     * @method GET
     * @tag 用户
     * @auth bearer
     * @param integer! id 用户 ID
     * @response 200 {"code":200,"message":"ok","data":{"id":1,"nickname":"张三","phone":"13800000000"}}
     */
    public function detail(int $id): array
    {
        return [];
    }

    /**
     * 注册接口不需要登录。
     *
     * @name 注册用户
     * @route /api/user/register
     * @method POST
     * @tag 用户
     * @auth none
     * @body {"nickname":"张三","phone":"13800000000","password":"123456"}
     * @response 200 {"code":200,"message":"注册成功","data":{"id":1}}
     */
    public function register(): array
    {
        return [];
    }

    /**
     * 上传头像，表单字段在 @bodyParam 里声明。
     *
     * @name 上传头像
     * @route /api/user/avatar
     * @method POST
     * @tag 用户
     * @auth bearer
     * @body multipart
     * @bodyParam file avatar 头像文件
     * @bodyParam integer userId 用户 ID
     * @response 200 {"code":200,"message":"ok","data":{"url":"/uploads/avatar/1.png"}}
     */
    public function avatar(): array
    {
        return [];
    }

    /**
     * 内部接口，不对外暴露。
     *
     * @name 内部统计
     * @route /api/user/internal
     * @method GET
     * @ignore
     */
    public function internal(): array
    {
        return [];
    }

    /**
     * 这个方法的注释里没有任何 apidoc 标签，不会被收集。
     */
    public function helperMethod(): void
    {
    }
}
