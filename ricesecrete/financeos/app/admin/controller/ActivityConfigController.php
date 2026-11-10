<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\ActivityConfigService;
use support\Request;

class ActivityConfigController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'api_parent_menu_slug' => 'activity-list',
            'apis' => [
                ['name' => '充值阶梯列表', 'slug' => 'activity-config:recharge-list', 'path' => '/admin/activities/*/recharge-bonuses', 'method' => 'GET', 'sort' => 1],
                ['name' => '充值阶梯创建', 'slug' => 'activity-config:recharge-create', 'path' => '/admin/activities/*/recharge-bonuses', 'method' => 'POST', 'sort' => 2],
                ['name' => '充值阶梯更新', 'slug' => 'activity-config:recharge-update', 'path' => '/admin/activities/*/recharge-bonuses/*', 'method' => 'PUT', 'sort' => 3],
                ['name' => '充值阶梯删除', 'slug' => 'activity-config:recharge-delete', 'path' => '/admin/activities/*/recharge-bonuses/*', 'method' => 'DELETE', 'sort' => 4],
                ['name' => '解锁编码列表', 'slug' => 'activity-config:password-list', 'path' => '/admin/activities/*/passwords', 'method' => 'GET', 'sort' => 5],
                ['name' => '解锁编码创建', 'slug' => 'activity-config:password-create', 'path' => '/admin/activities/*/passwords', 'method' => 'POST', 'sort' => 6],
                ['name' => '解锁编码删除', 'slug' => 'activity-config:password-delete', 'path' => '/admin/activities/*/passwords/*', 'method' => 'DELETE', 'sort' => 7],
                ['name' => '邀请阶梯列表', 'slug' => 'activity-config:invite-list', 'path' => '/admin/activities/*/invite-tiers', 'method' => 'GET', 'sort' => 8],
                ['name' => '邀请阶梯创建', 'slug' => 'activity-config:invite-create', 'path' => '/admin/activities/*/invite-tiers', 'method' => 'POST', 'sort' => 9],
                ['name' => '邀请阶梯更新', 'slug' => 'activity-config:invite-update', 'path' => '/admin/activities/*/invite-tiers/*', 'method' => 'PUT', 'sort' => 10],
                ['name' => '邀请阶梯删除', 'slug' => 'activity-config:invite-delete', 'path' => '/admin/activities/*/invite-tiers/*', 'method' => 'DELETE', 'sort' => 11],
            ],
        ];
    }

    public function __construct(private ?ActivityConfigService $service = null)
    {
        $this->service = $service ?? new ActivityConfigService();
    }

    public function rechargeBonuses(Request $request, int $activityId)
    {
        return success($this->service->rechargeBonuses($activityId));
    }

    public function storeRechargeBonus(Request $request, int $activityId)
    {
        return success($this->service->createRechargeBonus($activityId, $request->post()), '创建成功');
    }

    public function updateRechargeBonus(Request $request, int $activityId, int $id)
    {
        return success($this->service->updateRechargeBonus($activityId, $id, $request->post()), '更新成功');
    }

    public function destroyRechargeBonus(Request $request, int $activityId, int $id)
    {
        $this->service->deleteRechargeBonus($activityId, $id);

        return success(null, '删除成功');
    }

    public function passwords(Request $request, int $activityId)
    {
        return success($this->service->passwords($activityId));
    }

    public function storePassword(Request $request, int $activityId)
    {
        return success($this->service->createPassword($activityId, $request->post()), '创建成功');
    }

    public function destroyPassword(Request $request, int $activityId, int $id)
    {
        $this->service->deletePassword($activityId, $id);

        return success(null, '删除成功');
    }

    public function inviteTiers(Request $request, int $activityId)
    {
        return success($this->service->inviteTiers($activityId));
    }

    public function storeInviteTier(Request $request, int $activityId)
    {
        return success($this->service->createInviteTier($activityId, $request->post()), '创建成功');
    }

    public function updateInviteTier(Request $request, int $activityId, int $id)
    {
        return success($this->service->updateInviteTier($activityId, $id, $request->post()), '更新成功');
    }

    public function destroyInviteTier(Request $request, int $activityId, int $id)
    {
        $this->service->deleteInviteTier($activityId, $id);

        return success(null, '删除成功');
    }
}
