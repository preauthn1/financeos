<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\ReportService;
use support\Request;

class ReportComprehensiveController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '报表管理', 'slug' => 'report', 'path' => '/report', 'icon' => 'data-analysis', 'sort' => 50,
            ],
            'menu' => [
                'name' => '综合报表', 'slug' => 'report-comprehensive', 'path' => '/report/comprehensive',
                'icon' => 'pie-chart', 'component' => 'views/report/comprehensive/index', 'sort' => 3,
            ],
            'apis' => [
                ['name' => '综合报表', 'slug' => 'report-comprehensive:show', 'path' => '/admin/reports/comprehensive', 'method' => 'GET', 'sort' => 1],
            ],
        ];
    }

    protected ReportService $service;

    public function __construct(?ReportService $service = null)
    {
        $this->service = $service ?? new ReportService();
    }

    public function index(Request $request)
    {
        $filters = $request->only(['start_date', 'end_date']);
        return success($this->service->comprehensive($filters));
    }
}
