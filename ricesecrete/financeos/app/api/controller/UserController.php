<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\api\ApiUserService;
use app\service\MemberRealNameService;
use support\Request;

class UserController extends BaseController
{
    public function __construct(private ?ApiUserService $service = null)
    {
        $this->service = $service ?? new ApiUserService();
    }

    public function info(Request $request)
    {
        return success($this->service->profile($this->userId($request)));
    }

    public function changePassword(Request $request)
    {
        $this->service->changePassword(
            $this->userId($request),
            (string) $request->post('old_password', $request->post('oldpassword', '')),
            (string) $request->post('new_password', $request->post('password', ''))
        );
        return success(null, '修改成功');
    }

    public function changePaypwd(Request $request)
    {
        $this->service->changePayPassword(
            $this->userId($request),
            (string) $request->get('old_password', $request->post('old_password', '')),
            (string) $request->get('new_password', $request->post('new_password', $request->post('pay_password', '')))
        );
        return success(null, '修改成功');
    }

    public function bindBank(Request $request)
    {
        return success($this->service->bindBank($this->userId($request), $request->post()));
    }

    public function bindBankList(Request $request)
    {
        $type = (string) $request->get('type', $request->post('type', ''));
        return success($this->service->bindBankList($this->userId($request), $type));
    }

    public function signList(Request $request)
    {
        return success($this->service->signList($this->userId($request)));
    }

    public function sign(Request $request)
    {
        return success($this->service->sign($this->userId($request)), '签到成功');
    }

    public function realNameAuth(Request $request)
    {
        $data = array_merge($request->post(), ['user_id' => $this->userId($request)]);
        $auth = (new MemberRealNameService())->create($data);
        return success($auth, '提交成功');
    }

    public function getRealNameAuth(Request $request)
    {
        $auth = \app\model\member\UserRealNameAuthModel::where('user_id', $this->userId($request))
            ->orderByDesc('id')->first();
        return success($auth);
    }

    public function getBalanceLog(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success((new \app\service\api\ApiInvestmentService())->profitLog(
            $this->userId($request), $page, $limit,
            (string) $request->get('started_at', $request->get('start_time', '')),
            (string) $request->get('ended_at', $request->get('end_time', ''))
        ));
    }

    public function getTurntable(Request $request)
    {
        return success((new \app\service\api\ApiTurntableService())->getTurntable($this->userId($request)));
    }

    public function onTurntable(Request $request)
    {
        $result = (new \app\service\api\ApiTurntableService())->spin(
            $this->userId($request),
            (int) $request->post('id', $request->get('id', 0))
        );
        return json_response($result);
    }

    public function transferMoney(Request $request)
    {
        $this->service->transferMoney(
            $this->userId($request),
            (string) $request->get('amount', $request->post('amount', '0')),
            (string) $request->get('password', $request->post('password', ''))
        );
        return success(null, '转账成功');
    }

    public function promotionRewardList(Request $request)
    {
        return success($this->service->promotionRewardList($this->userId($request)));
    }

    public function receivePromotionReward(Request $request, int $day = 1)
    {
        $this->service->receivePromotionReward($this->userId($request), $day ?: (int) $request->get('day', 1));
        return success(null, '领取成功');
    }
}
