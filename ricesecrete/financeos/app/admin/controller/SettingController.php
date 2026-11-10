<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use support\Request;
use app\service\SettingService;
use app\service\SystemConfigService;

class SettingController extends BaseController
{
    use DefinesAdminMenu;

public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '系统配置',
                'slug' => 'system-config',
                'path' => '/system-config',
                'icon' => 'setting',
                'sort' => 30,
            ],
            'menu' => [
                'name' => '参数配置',
                'slug' => 'setting-menu',
                'path' => '/system-config/setting',
                'icon' => 'tools',
                'component' => 'views/system/setting/index',
                'sort' => 1,
            ],
            'apis' => [
                ['name' => '参数配置详情', 'slug' => 'setting:bundle', 'path' => '/admin/system-config', 'method' => 'GET', 'sort' => 1],
                ['name' => '保存参数配置', 'slug' => 'setting:bundle-save', 'path' => '/admin/system-config', 'method' => 'PUT', 'sort' => 2],
            ],
        ];
    }

    protected SettingService $settingService;

    protected SystemConfigService $systemConfigService;

    public function __construct(?SettingService $settingService = null, ?SystemConfigService $systemConfigService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
        $this->systemConfigService = $systemConfigService ?? new SystemConfigService($this->settingService);
    }

    public function configBundle(Request $request)
    {
        return success($this->systemConfigService->bundle());
    }

    public function saveConfigBundle(Request $request)
    {
        $payload = $request->post();
        $this->systemConfigService->save($payload);
        return success(null, '保存成功');
    }
}
