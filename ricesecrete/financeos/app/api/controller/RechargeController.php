<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiRechargeService;
use support\Request;

class RechargeController extends BaseController
{
    public function __construct(private ?ApiRechargeService $service = null)
    {
        $this->service = $service ?? new ApiRechargeService();
    }

    public function index(Request $request)
    {
        return success($this->service->channels($this->userId($request)));
    }

    public function getRechargeAddress(Request $request)
    {
        $platformId = (int) $request->get('platform_id', $request->get('pay_platform_id', 0));
        $money = (string) $request->get('money', '0');
        if ($platformId <= 0) {
            $platform = \app\model\pay\PayPlatformModel::where('recharge_is_show', 1)->where('status', 1)->first();
            $platformId = (int) ($platform?->id ?? 0);
        }
        return success($this->service->createOrder($this->userId($request), $platformId, $money, (string) $request->getRealIp()));
    }

    public function offlinePay(Request $request)
    {
        $data = $request->post();
        $data['ip'] = (string) $request->getRealIp();
        return success($this->service->offlinePay($this->userId($request), $data), '提交成功');
    }

    public function lists(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->userOrders($this->userId($request), $page, $limit));
    }
}
