<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\SettingService;
use app\service\TradeDividendService;
use support\Request;

class TradeDividendConfigController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '活动管理', 'slug' => 'activity', 'path' => '/activity', 'icon' => 'present', 'sort' => 55,
            ],
            'menu' => [
                'name' => '交易分红配置', 'slug' => 'activity-trade-dividend', 'path' => '/activity/trade-dividend',
                'icon' => 'coin', 'component' => 'views/ops/trade-dividend/index', 'sort' => 20,
            ],
            'apis' => [
                ['name' => '分红配置读取', 'slug' => 'activity-trade-dividend:show', 'path' => '/admin/trade-dividend/config', 'method' => 'GET', 'sort' => 1],
                ['name' => '分红配置保存', 'slug' => 'activity-trade-dividend:save', 'path' => '/admin/trade-dividend/config', 'method' => 'PUT', 'sort' => 2],
            ],
        ];
    }

    public function show(Request $request)
    {
        return success((new TradeDividendService())->getConfig());
    }

    public function save(Request $request)
    {
        $config = $request->post();
        (new SettingService())->setValue('trade_dividend_config', $config, '每日交易分红配置');

        return success(null, '保存成功');
    }
}
