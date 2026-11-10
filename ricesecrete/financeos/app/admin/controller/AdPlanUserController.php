<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\AdPlanService;
use support\Request;

class AdPlanUserController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => ['name' => '任务中心', 'slug' => 'task', 'path' => '/task', 'icon' => 'list', 'sort' => 45],
            'menu' => [
                'name' => '广告计划用户列表', 'slug' => 'task-ad-plan-user', 'path' => '/task/ad-plan-user',
                'icon' => 'user', 'component' => 'views/task/ad-plan-user/index', 'sort' => 5,
            ],
            'apis' => [
                ['name' => '广告计划用户列表', 'slug' => 'task-ad-plan-user:list', 'path' => '/admin/ad-plan-users', 'method' => 'GET', 'sort' => 1],
                ['name' => '用户广告计划详情', 'slug' => 'task-ad-plan-user:show', 'path' => '/admin/ad-plan-users/*/plan', 'method' => 'GET', 'sort' => 2],
                ['name' => '调整用户广告计划', 'slug' => 'task-ad-plan-user:save', 'path' => '/admin/ad-plan-users/*/plan', 'method' => 'PUT', 'sort' => 3],
            ],
        ];
    }

    public function __construct(private ?AdPlanService $service = null)
    {
        $this->service = $service ?? new AdPlanService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->listUsers($page, $limit, $request->only([
            'id', 'username', 'ad_plan_id', 'next_id', 'day',
        ])));
    }

    public function plan(Request $request, int $userId)
    {
        return success($this->service->showUserPlan($userId));
    }

    public function savePlan(Request $request, int $userId)
    {
        return success($this->service->saveUserDaysConfig($userId, $request->post()), '保存成功');
    }
}
