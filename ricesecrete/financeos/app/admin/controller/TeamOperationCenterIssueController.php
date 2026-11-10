<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\TeamOperationCenterIssueService;
use support\Request;

class TeamOperationCenterIssueController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '运营中心', 'slug' => 'ops', 'path' => '/ops', 'icon' => 'office-building', 'sort' => 52,
            ],
            'menu' => [
                'name' => '周期数据', 'slug' => 'ops-period', 'path' => '/ops/period',
                'icon' => 'calendar', 'component' => 'views/ops/period/index', 'sort' => 2,
            ],
            'apis' => [
                ['name' => '周期数据列表', 'slug' => 'ops-period:list', 'path' => '/admin/operation-center-issues', 'method' => 'GET', 'sort' => 1],
            ],
        ];
    }

    protected TeamOperationCenterIssueService $service;

    public function __construct(?TeamOperationCenterIssueService $service = null)
    {
        $this->service = $service ?? new TeamOperationCenterIssueService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['user_id', 'submit_user_id', 'issue', 'status', 'type', 'start_date', 'end_date']);
        return success($this->service->list($page, $limit, $filters));
    }
}
