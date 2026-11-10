<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\NoticeService;
use support\Request;

class NoticeController
{
    public function index(Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(50, max(1, (int) $request->get('limit', 10)));
        $lang = (string) $request->get('lang', 'zh-cn');
        return success((new NoticeService())->publicList($page, $limit, $lang));
    }
}
