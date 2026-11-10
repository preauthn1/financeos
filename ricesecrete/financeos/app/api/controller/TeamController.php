<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiTeamService;
use support\Request;

class TeamController extends BaseController
{
    public function teamInfo(Request $request)
    {
        return success((new ApiTeamService())->teamInfo($this->userId($request), [
            'start_date' => (string) $request->get('start_date', ''),
            'end_date'   => (string) $request->get('end_date', ''),
        ]));
    }

    public function detail(Request $request, int $level = 1)
    {
        return success((new ApiTeamService())->detail($this->userId($request), $level));
    }
}
