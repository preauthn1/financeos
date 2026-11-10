<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\CustomerLinkService;
use support\Request;

class CustomerServiceController
{
    public function index(Request $request)
    {
        return success((new CustomerLinkService())->publicList());
    }
}
