<?php

declare(strict_types=1);

namespace App\Api\Controller;

/**
 * 订单接口示例，演示参数默认值、枚举和废弃标记。
 */
class OrderController
{
    /**
     * @name 订单列表
     * @route /api/order/list
     * @method GET
     * @tag 订单
     * @auth bearer
     * @param integer page=1 页码
     * @param string status=all 订单状态
     * @response 200 {"code":200,"message":"ok","data":{"total":3,"list":[{"id":10,"orderNo":"20260930001","amount":99.5,"status":"paid"}]}}
     */
    public function index(): array
    {
        return [];
    }

    /**
     * @name 订单详情
     * @route /api/order/{orderNo}
     * @method GET
     * @tag 订单
     * @auth bearer
     * @param string orderNo 订单号
     * @response 200 {"code":200,"message":"ok","data":{"orderNo":"20260930001","amount":99.5,"items":[{"name":"二手教材","quantity":1}]}}
     */
    public function detail(string $orderNo): array
    {
        return [];
    }

    /**
     * @name 创建订单
     * @route /api/order/create
     * @method POST
     * @tag 订单
     * @auth bearer
     * @body {"productId":1,"quantity":1,"remark":"周末面交"}
     * @response 200 {"code":200,"message":"下单成功","data":{"orderNo":"20260930001"}}
     * @response 400 {"code":400,"message":"库存不足"}
     * @example {"productId":1,"quantity":1}
     */
    public function create(): array
    {
        return [];
    }

    /**
     * @name 取消订单（旧接口）
     * @route /api/order/cancel
     * @method POST
     * @tag 订单
     * @auth bearer
     * @deprecated
     * @body {"orderNo":"20260930001"}
     * @response 200 {"code":200,"message":"已取消"}
     */
    public function cancel(): array
    {
        return [];
    }
}
