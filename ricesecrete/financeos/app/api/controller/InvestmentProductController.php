<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiInvestmentService;
use support\Request;

class InvestmentProductController extends BaseController
{
    public function __construct(private ?ApiInvestmentService $service = null)
    {
        $this->service = $service ?? new ApiInvestmentService();
    }

    public function index(Request $request)
    {
        return success($this->service->products());
    }

    public function detail(Request $request)
    {
        return success($this->service->profitSummary($this->userId($request)));
    }

    public function buy(Request $request)
    {
        $productId = (int) $request->post('id', $request->post('product_id', 0));
        $amount = (string) $request->post('amount', $request->post('money', '0'));
        return success($this->service->buy($this->userId($request), $productId, $amount), '购买成功');
    }

    public function speedSettle(Request $request)
    {
        $code = (string) ($request->post('code')
            ?? $request->post('speed_code')
            ?? $request->post('password')
            ?? '');
        return success($this->service->speedSettle(
            $this->userId($request),
            (int) $request->post('order_id', 0),
            trim($code)
        ), '加速结算成功');
    }

    public function orderList(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $status = $request->get('status');
        return success($this->service->orderList(
            $this->userId($request), $page, $limit,
            $status !== null && $status !== '' ? (int) $status : null
        ));
    }

    public function orderCount(Request $request)
    {
        return success($this->service->orderStats($this->userId($request)));
    }
}
