<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\ReportService;
use support\Request;

class ReportFinanceController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '报表管理', 'slug' => 'report', 'path' => '/report', 'icon' => 'data-analysis', 'sort' => 50,
            ],
            'menu' => [
                'name' => '财务日报', 'slug' => 'report-finance-daily', 'path' => '/report/finance-daily',
                'icon' => 'money', 'component' => 'views/report/finance-daily/index', 'sort' => 2,
            ],
            'apis' => [
                ['name' => '财务日报', 'slug' => 'report-finance-daily:list', 'path' => '/admin/reports/finance-daily', 'method' => 'GET', 'sort' => 1],
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
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['start_date', 'end_date']);
        return success($this->service->financeDaily($page, $limit, $filters));
    }
}
