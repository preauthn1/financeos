<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\ReportService;
use support\Request;

class ReportController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '报表管理',
                'slug' => 'report',
                'path' => '/report',
                'icon' => 'data-analysis',
                'sort' => 50,
            ],
            'menu' => [
                'name' => '日报表',
                'slug' => 'report-daily',
                'path' => '/report/daily',
                'icon' => 'calendar',
                'component' => 'views/report/daily/index',
                'sort' => 1,
            ],
            'apis' => [
                ['name' => '日报表', 'slug' => 'report-daily:list', 'path' => '/admin/reports/daily', 'method' => 'GET', 'sort' => 1],
            ],
        ];
    }

    protected ReportService $service;

    public function __construct(?ReportService $service = null)
    {
        $this->service = $service ?? new ReportService();
    }

    public function daily(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['start_date', 'end_date', 'agent_id']);
        return success($this->service->daily($page, $limit, $filters));
    }
}
