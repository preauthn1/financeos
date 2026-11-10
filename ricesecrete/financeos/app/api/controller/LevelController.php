<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiLevelService;
use support\Request;

class LevelController extends BaseController
{
    public function __construct(private ?ApiLevelService $service = null)
    {
        $this->service = $service ?? new ApiLevelService();
    }

    public function upLevel(Request $request, int $level_id = 0)
    {
        $levelId = $level_id ?: (int) $request->post('level_id', 0);
        return success($this->service->upLevel($this->userId($request), $levelId));
    }

    public function checkPassword(Request $request)
    {
        return success($this->service->checkPassword(
            (string) ($request->post('password') ?? $request->get('password', '')),
            (int) ($request->post('level_id') ?? $request->get('level_id', 0)),
            (int) ($request->post('type') ?? $request->get('type', 0))
        ));
    }
}
