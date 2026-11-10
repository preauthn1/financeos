<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiMessageService;
use support\Request;

class MessageController extends BaseController
{
    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success((new ApiMessageService())->list($this->userId($request), $page, $limit));
    }

    public function detail(Request $request)
    {
        $id = (int) $request->get('id', 0);
        return success((new ApiMessageService())->detail($this->userId($request), $id));
    }

    public function popList(Request $request)
    {
        return success((new ApiMessageService())->popList($this->userId($request)));
    }
}
