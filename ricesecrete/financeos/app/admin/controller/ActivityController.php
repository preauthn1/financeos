<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\ActivityService;
use support\Request;

class ActivityController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '活动管理', 'slug' => 'activity', 'path' => '/activity', 'icon' => 'present', 'sort' => 55,
            ],
            'menu' => [
                'name' => '活动列表', 'slug' => 'activity-list', 'path' => '/activity/list',
                'icon' => 'tickets', 'component' => 'views/activity/list/index', 'sort' => 1,
            ],
            'apis' => [
                ['name' => '活动列表', 'slug' => 'activity-list:list', 'path' => '/admin/activities', 'method' => 'GET', 'sort' => 1],
                ['name' => '活动详情', 'slug' => 'activity-list:show', 'path' => '/admin/activities/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '活动创建', 'slug' => 'activity-list:create', 'path' => '/admin/activities', 'method' => 'POST', 'sort' => 3],
                ['name' => '活动更新', 'slug' => 'activity-list:update', 'path' => '/admin/activities/*', 'method' => 'PUT', 'sort' => 4],
                ['name' => '活动状态', 'slug' => 'activity-list:toggle', 'path' => '/admin/activities/*/status', 'method' => 'PUT', 'sort' => 5],
            ],
        ];
    }

    protected ActivityService $service;

    public function __construct(?ActivityService $service = null)
    {
        $this->service = $service ?? new ActivityService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['keyword', 'type', 'status']);
        return success($this->service->list($page, $limit, $filters));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'title', 'thumb', 'started_at', 'ended_at', 'content', 'user_type', 'trigger_type',
            'type', 'reward_type', 'reward', 'times', 'day_times', 'day_times_type', 'user_amount',
            'num', 'password', 'expired_at', 'status', 'extra',
        ]);
        return success($this->service->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only([
            'title', 'thumb', 'started_at', 'ended_at', 'content', 'user_type', 'trigger_type',
            'type', 'reward_type', 'reward', 'times', 'day_times', 'day_times_type', 'user_amount',
            'num', 'password', 'expired_at', 'status', 'extra',
        ]);
        return success($this->service->update($id, $data), '更新成功');
    }

    public function toggleStatus(Request $request, int $id)
    {
        $status = (int) $request->post('status', 0);
        return success($this->service->toggleStatus($id, $status), '操作成功');
    }
}
