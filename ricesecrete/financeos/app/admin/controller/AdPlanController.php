<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\AdPlanService;
use support\Request;

class AdPlanController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => ['name' => '任务中心', 'slug' => 'task', 'path' => '/task', 'icon' => 'list', 'sort' => 45],
            'menu' => [
                'name' => '广告位购买计划配置', 'slug' => 'task-ad-plan', 'path' => '/task/ad-plan',
                'icon' => 'calendar', 'component' => 'views/task/ad-plan/index', 'sort' => 4,
            ],
            'apis' => [
                ['name' => '广告计划列表', 'slug' => 'task-ad-plan:list', 'path' => '/admin/ad-plans', 'method' => 'GET', 'sort' => 1],
                ['name' => '广告计划元数据', 'slug' => 'task-ad-plan:meta', 'path' => '/admin/ad-plans/meta', 'method' => 'GET', 'sort' => 2],
                ['name' => '广告计划详情', 'slug' => 'task-ad-plan:show', 'path' => '/admin/ad-plans/*', 'method' => 'GET', 'sort' => 3],
                ['name' => '广告计划保存', 'slug' => 'task-ad-plan:save', 'path' => '/admin/ad-plans', 'method' => 'POST', 'sort' => 4],
                ['name' => '广告计划更新', 'slug' => 'task-ad-plan:update', 'path' => '/admin/ad-plans/*', 'method' => 'PUT', 'sort' => 5],
                ['name' => '广告计划状态', 'slug' => 'task-ad-plan:toggle', 'path' => '/admin/ad-plans/*/toggle', 'method' => 'PUT', 'sort' => 6],
                ['name' => '广告计划删除', 'slug' => 'task-ad-plan:delete', 'path' => '/admin/ad-plans/*', 'method' => 'DELETE', 'sort' => 7],
                ['name' => '广告计划日配置', 'slug' => 'task-ad-plan:days', 'path' => '/admin/ad-plans/*/days', 'method' => 'GET', 'sort' => 8],
                ['name' => '广告计划日配置保存', 'slug' => 'task-ad-plan:days-save', 'path' => '/admin/ad-plan-days', 'method' => 'POST', 'sort' => 9],
                ['name' => '广告计划日配置删除', 'slug' => 'task-ad-plan:days-delete', 'path' => '/admin/ad-plan-days/*', 'method' => 'DELETE', 'sort' => 10],
            ],
        ];
    }

    public function __construct(private ?AdPlanService $service = null)
    {
        $this->service = $service ?? new AdPlanService();
    }

    public function index()
    {
        return success($this->service->listPlans());
    }

    public function meta(Request $request)
    {
        $excludeId = (int) $request->get('exclude_id', 0);
        return success($this->service->meta($excludeId > 0 ? $excludeId : null));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->showPlan($id));
    }

    public function store(Request $request)
    {
        return success($this->service->savePlan($request->post()), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        return success($this->service->savePlan($request->post(), $id), '更新成功');
    }

    public function toggle(Request $request, int $id)
    {
        $status = (int) $request->post('status', 0);
        return success($this->service->toggleStatus($id, $status), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->deletePlan($id);
        return success(null, '删除成功');
    }

    public function days(Request $request, int $id)
    {
        return success($this->service->listDayConfigs($id));
    }

    public function storeDay(Request $request)
    {
        return success($this->service->saveDayConfig($request->post()), '保存成功');
    }

    public function destroyDay(Request $request, int $id)
    {
        $this->service->deleteDayConfig($id);
        return success(null, '删除成功');
    }
}
