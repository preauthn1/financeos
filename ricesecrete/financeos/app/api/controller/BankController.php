<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\SettingService;
use support\Request;

class BankController extends BaseController
{
    public function index(Request $request)
    {
        $banks = (new SettingService())->getValue('bank_list', []);
        return success(is_array($banks) ? $banks : []);
    }
}
