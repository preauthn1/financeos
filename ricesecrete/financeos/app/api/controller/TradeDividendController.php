<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\TradeDividendService;
use support\Request;

class TradeDividendController extends BaseController
{
    public function index(Request $request)
    {
        return success((new TradeDividendService())->index($this->userId($request)));
    }

    public function claim(Request $request)
    {
        return success((new TradeDividendService())->claim($this->userId($request)), '领取成功');
    }
}
