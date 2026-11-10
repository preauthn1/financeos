<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\ActivityRecordService;
use support\Request;

class ActivityRecordController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '活动管理', 'slug' => 'activity', 'path' => '/activity', 'icon' => 'present', 'sort' => 55,
            ],
            'menu' => [
                'name' => '活动记录', 'slug' => 'activity-record', 'path' => '/activity/record',
                'icon' => 'document', 'component' => 'views/activity/record/index', 'sort' => 2,
            ],
            'apis' => [
                ['name' => '活动记录列表', 'slug' => 'activity-record:list', 'path' => '/admin/activity-records', 'method' => 'GET', 'sort' => 1],
            ],
        ];
    }

    protected ActivityRecordService $service;

    public function __construct(?ActivityRecordService $service = null)
    {
        $this->service = $service ?? new ActivityRecordService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['user_id', 'activity_id', 'status', 'is_virtual', 'start_date', 'end_date']);
        return success($this->service->list($page, $limit, $filters));
    }
}
