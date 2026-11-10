<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\ReportService;
use support\Request;

class ReportRegisterController extends BaseController
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
                'name' => '注册报表',
                'slug' => 'report-register',
                'path' => '/report/register',
                'icon' => 'user',
                'component' => 'views/report/register/index',
                'sort' => 4,
            ],
            'apis' => [
                ['name' => '注册报表', 'slug' => 'report-register:list', 'path' => '/admin/reports/register', 'method' => 'GET', 'sort' => 1],
                ['name' => '注册报表明细', 'slug' => 'report-register:users', 'path' => '/admin/reports/register/users', 'method' => 'GET', 'sort' => 2],
            ],
        ];
    }

    public function __construct(private ?ReportService $service = null)
    {
        $this->service = $service ?? new ReportService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['start_date', 'end_date', 'agent_id']);

        return success($this->service->registerReport($page, $limit, $filters));
    }

    public function users(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['stat_date', 'agent_id', 'keyword', 'has_recharge', 'is_virtual']);

        return success($this->service->registerReportUsers($page, $limit, $filters));
    }
}
