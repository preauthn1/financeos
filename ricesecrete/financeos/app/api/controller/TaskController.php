<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiTaskService;
use app\service\api\ApiVideoService;
use support\Request;

class TaskController extends BaseController
{
    public function __construct(
        private ?ApiVideoService $service = null,
        private ?ApiTaskService $taskService = null,
    ) {
        $this->service = $service ?? new ApiVideoService();
        $this->taskService = $taskService ?? new ApiTaskService();
    }

    public function videoList(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->videoList(
            $page, $limit,
            $request->get('cate') ? (int) $request->get('cate') : null,
            (bool) $request->get('is_random', false)
        ));
    }

    public function videoDetail(Request $request)
    {
        $id = (int) $request->get('id', $request->get('video_id', 0));
        return success($this->service->videoDetail($id));
    }

    public function recommendVideoList(Request $request)
    {
        return success($this->service->recommendList((int) $request->get('id', 0)));
    }

    public function list(Request $request)
    {
        return success($this->service->taskList($this->userId($request)));
    }

    public function taskInfo(Request $request)
    {
        return success($this->taskService->taskInfo($this->userId($request)));
    }

    public function recReward(Request $request)
    {
        return success($this->service->recReward($this->userId($request)));
    }

    public function getRecReward(Request $request)
    {
        return success($this->service->getRecReward($this->userId($request)));
    }
}
