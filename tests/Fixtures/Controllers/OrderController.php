<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Fixtures\Controllers;

final class OrderController
{
    /**
     * @name 订单详情
     * @desc 获取订单详情
     * @route /order/{id}
     * @method GET
     * @group 订单
     * @auth bearer
     * @param int id 订单 ID
     * @response {"code":200,"data":{"order_no":"20260930001","amount":"99.00"}}
     */
    public function detail(): void
    {
    }
}
