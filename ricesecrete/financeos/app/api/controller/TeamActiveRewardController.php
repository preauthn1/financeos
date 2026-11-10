<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiTeamActiveRewardService;
use support\Request;

class TeamActiveRewardController extends BaseController
{
    public function __construct(private ?ApiTeamActiveRewardService $service = null)
    {
        $this->service = $service ?? new ApiTeamActiveRewardService();
    }

    public function indexV2(Request $request)
    {
        return success($this->service->indexV2($this->userId($request)));
    }

    public function getRewardV2(Request $request)
    {
        $this->service->getRewardV2($this->userId($request), (int) $request->get('id', $request->get('reward_id', 0)));
        return success(null, '领取成功');
    }

    public function addProxyCenterData(Request $request)
    {
        return success($this->service->addProxyCenterData($this->userId($request), $request->post()));
    }

    public function addGroupCenterData(Request $request)
    {
        return success($this->service->addGroupCenterData($this->userId($request), $request->post()));
    }

    public function addCityCenterData(Request $request)
    {
        return success($this->service->addCityCenterData($this->userId($request), $request->post()));
    }

    public function handleCenterReward(Request $request)
    {
        $this->service->handleCenterReward($this->userId($request), (int) $request->get('issue_id', 0));
        return success(null, '领取成功');
    }
}
