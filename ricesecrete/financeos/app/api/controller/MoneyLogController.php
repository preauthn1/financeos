<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiMoneyLogService;
use support\Request;

class MoneyLogController extends BaseController
{
    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['type', 'detail_type', 'direction', 'start_date', 'end_date']);
        return success((new ApiMoneyLogService())->list($this->userId($request), $page, $limit, $filters));
    }
}
