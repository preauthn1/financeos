<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\InvestmentSpeedCodeService;
use support\Request;

class InvestmentSpeedCodeController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            // 入口挂在「AI托管配置」工具栏，不再单独出侧边栏菜单
            'api_parent_menu_slug' => 'task-ai-config',
            'apis' => [
                ['name' => '加速码列表', 'slug' => 'task-speed-code:list', 'path' => '/admin/investment-speed-codes', 'method' => 'GET', 'sort' => 1],
                ['name' => '加速码创建', 'slug' => 'task-speed-code:create', 'path' => '/admin/investment-speed-codes', 'method' => 'POST', 'sort' => 2],
                ['name' => '加速码更新', 'slug' => 'task-speed-code:update', 'path' => '/admin/investment-speed-codes/*', 'method' => 'PUT', 'sort' => 3],
                ['name' => '加速码删除', 'slug' => 'task-speed-code:delete', 'path' => '/admin/investment-speed-codes/*', 'method' => 'DELETE', 'sort' => 4],
            ],
        ];
    }

    public function __construct(private ?InvestmentSpeedCodeService $service = null)
    {
        $this->service = $service ?? new InvestmentSpeedCodeService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);

        return success($this->service->list($page, $limit, $request->get()));
    }

    public function store(Request $request)
    {
        return success($this->service->create($request->post()), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        return success($this->service->update($id, $request->post()), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);

        return success(null, '删除成功');
    }
}
