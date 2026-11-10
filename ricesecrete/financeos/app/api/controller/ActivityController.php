<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiActivityService;
use support\Request;

class ActivityController extends BaseController
{
    public function __construct(private ?ApiActivityService $service = null)
    {
        $this->service = $service ?? new ApiActivityService();
    }

    public function index(Request $request)
    {
        $type = $request->get('type');
        $typeFilter = null;
        if ($type !== null && $type !== '') {
            $typeFilter = str_contains((string) $type, ',')
                ? array_values(array_filter(array_map('intval', explode(',', (string) $type))))
                : (int) $type;
        }

        return success($this->service->list($this->userId($request), $typeFilter));
    }

    public function join(Request $request)
    {
        $activityId = (int) $request->get('activity_id', $request->get('id', 0));

        return success($this->service->join(
            $this->userId($request),
            $activityId,
            (string) $request->get('password', '')
        ), '参与成功');
    }

    public function getReward(Request $request)
    {
        $activityId = (int) $request->get('activity_id', $request->get('id', 0));
        $bonusId = (int) $request->get('recharge_bonus_id', 0);

        return success($this->service->getReward($this->userId($request), $activityId, $bonusId), '领取成功');
    }

    public function rechargeBonus(Request $request)
    {
        $activityId = (int) $request->get('activity_id', 0);

        return success($this->service->rechargeBonusList($this->userId($request), $activityId));
    }

    public function rechargeNow(Request $request)
    {
        return success($this->service->rechargeNow(
            $this->userId($request),
            (int) $request->get('activity_id', 0),
            (int) $request->get('recharge_bonus_id', 0)
        ));
    }

    public function claimRegisterTrial(Request $request)
    {
        return success($this->service->claimRegisterTrial($this->userId($request)), '领取成功');
    }
}
