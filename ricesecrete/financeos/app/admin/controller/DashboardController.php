<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use support\Request;
use app\service\DashboardStatService;

class DashboardController extends BaseController
{
    use DefinesAdminMenu;

    protected DashboardStatService $statService;

    public function __construct(?DashboardStatService $statService = null)
    {
        $this->statService = $statService ?? new DashboardStatService();
    }

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '控制台',
                'slug' => 'console',
                'path' => '/console',
                'icon' => 'dashboard',
                'sort' => 0,
                'children' => [
                    [
                        'name' => '首页总览',
                        'slug' => 'dashboard',
                        'path' => '/console/dashboard',
                        'icon' => 'dashboard',
                        'component' => 'views/Dashboard/Analysis',
                        'sort' => 1,
                    ],
                ],
            ],
            'api_parent_menu_slug' => 'dashboard',
            'apis' => [
                ['name' => '运营概览', 'slug' => 'dashboard:overview', 'path' => '/admin/dashboard/overview', 'method' => 'GET', 'sort' => 1],
                ['name' => '运营趋势', 'slug' => 'dashboard:trend', 'path' => '/admin/dashboard/trend', 'method' => 'GET', 'sort' => 2],
                ['name' => '待办提醒', 'slug' => 'dashboard:alerts', 'path' => '/admin/dashboard/alerts', 'method' => 'GET', 'sort' => 3],
            ],
        ];
    }

    public function overview(Request $request)
    {
        return success($this->statService->overview());
    }

    public function trend(Request $request)
    {
        $days = (int) $request->get('days', 7);
        return success($this->statService->trend($days));
    }

    public function alerts(Request $request)
    {
        return success($this->statService->alerts());
    }

    public function total(Request $request)
    {
        return success($this->statService->legacyTotal());
    }

    public function userAccessSource(Request $request)
    {
        $overview = $this->statService->overview();
        return success([
            ['name' => '今日充值笔数', 'value' => (int) $overview['today']['recharge_count']],
            ['name' => '今日提现笔数', 'value' => (int) $overview['today']['withdraw_count']],
            ['name' => '今日注册', 'value' => (int) $overview['today']['register']],
            ['name' => '今日活跃', 'value' => (int) $overview['today']['active']],
        ]);
    }

    public function weeklyUserActivity(Request $request)
    {
        $trend = $this->statService->trend(7);
        return success(array_map(static fn ($row) => [
            'name'  => $row['date'],
            'value' => (int) $row['register'],
        ], $trend['items']));
    }

    public function monthlySales(Request $request)
    {
        $trend = $this->statService->trend(7);
        return success(array_map(static fn ($row) => [
            'name'     => $row['date'],
            'estimate' => (int) $row['recharge'],
            'actual'   => (int) $row['withdraw'],
        ], $trend['items']));
    }
}
