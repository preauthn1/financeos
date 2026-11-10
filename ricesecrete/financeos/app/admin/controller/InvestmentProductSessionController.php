<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\InvestmentProductSessionService;
use support\Request;

class InvestmentProductSessionController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'api_parent_menu_slug' => 'task-ai-config',
            'apis' => [
                ['name' => '开放时间段列表', 'slug' => 'task-ai-session:list', 'path' => '/admin/investment-product-sessions', 'method' => 'GET', 'sort' => 1],
                ['name' => '开放时间段创建', 'slug' => 'task-ai-session:create', 'path' => '/admin/investment-product-sessions', 'method' => 'POST', 'sort' => 2],
                ['name' => '开放时间段更新', 'slug' => 'task-ai-session:update', 'path' => '/admin/investment-product-sessions/*', 'method' => 'PUT', 'sort' => 3],
                ['name' => '开放时间段删除', 'slug' => 'task-ai-session:delete', 'path' => '/admin/investment-product-sessions/*', 'method' => 'DELETE', 'sort' => 4],
                ['name' => '开放时间段状态', 'slug' => 'task-ai-session:toggle', 'path' => '/admin/investment-product-sessions/*/toggle', 'method' => 'PUT', 'sort' => 5],
            ],
        ];
    }

    public function __construct(private ?InvestmentProductSessionService $service = null)
    {
        $this->service = $service ?? new InvestmentProductSessionService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);

        return success($this->service->list($page, $limit));
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

    public function toggle(Request $request, int $id)
    {
        return success($this->service->toggleStatus($id), '状态已更新');
    }
}
