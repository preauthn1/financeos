<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiBrokeragePoolService;
use support\Request;

class BrokeragePoolController extends BaseController
{
    public function index(Request $request)
    {
        return success((new ApiBrokeragePoolService())->index($this->userId($request)));
    }

    public function receive(Request $request)
    {
        return success((new ApiBrokeragePoolService())->receive($this->userId($request)), '领取成功');
    }
}
