<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use support\Request;
use app\service\LogService;

class LogController extends BaseController
{
    use DefinesAdminMenu;

public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '权限管理',
                'slug' => 'auth',
                'path' => '/auth',
                'icon' => 'lock',
                'sort' => 20,
            ],
            'menu' => [
                'name' => '操作日志',
                'slug' => 'log-menu',
                'path' => '/auth/log',
                'icon' => 'documentation',
                'component' => 'views/system/log/index',
                'sort' => 4,
            ],
            'apis' => [
                ['name' => '日志列表', 'slug' => 'log:list', 'path' => '/admin/logs', 'method' => 'GET', 'sort' => 1],
            ],
        ];
    }

    protected LogService $logService;

    public function __construct(?LogService $logService = null)
    {
        $this->logService = $logService ?? new LogService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['admin_id', 'module', 'start_date', 'end_date']);
        return success($this->logService->list($page, $limit, $filters));
    }
}
