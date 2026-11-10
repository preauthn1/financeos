<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiWithdrawService;
use support\Request;

class WithdrawalController extends BaseController
{
    public function __construct(private ?ApiWithdrawService $service = null)
    {
        $this->service = $service ?? new ApiWithdrawService();
    }

    public function index(Request $request)
    {
        return success($this->service->config($this->userId($request)));
    }

    public function store(Request $request)
    {
        return success($this->service->store($this->userId($request), $request->post(), (string) $request->getRealIp()), '提交成功');
    }

    public function depositUpgrade(Request $request)
    {
        return success(
            $this->service->depositUpgrade(
                $this->userId($request),
                (int) $request->post('target_level', $request->post('level', 0))
            ),
            '保证金升级成功'
        );
    }
}
