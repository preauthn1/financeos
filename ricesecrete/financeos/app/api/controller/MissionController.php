<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiVideoService;
use support\Request;

class MissionController extends BaseController
{
    public function __construct(private ?ApiVideoService $service = null)
    {
        $this->service = $service ?? new ApiVideoService();
    }

    public function addVideoTask(Request $request)
    {
        $this->service->addVideoTask(
            $this->userId($request),
            (int) $request->get('level_id', 0),
            (int) $request->get('video_id', 0)
        );
        return success(null, '任务完成');
    }
}
