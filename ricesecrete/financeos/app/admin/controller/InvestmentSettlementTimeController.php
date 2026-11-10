<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\InvestmentSettlementTimeService;
use support\Request;

class InvestmentSettlementTimeController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'api_parent_menu_slug' => 'task-ai-config',
            'apis' => [
                ['name' => '结算时间点列表', 'slug' => 'task-ai-settlement:list', 'path' => '/admin/investment-settlement-times', 'method' => 'GET', 'sort' => 1],
                ['name' => '结算时间点创建', 'slug' => 'task-ai-settlement:create', 'path' => '/admin/investment-settlement-times', 'method' => 'POST', 'sort' => 2],
                ['name' => '结算时间点更新', 'slug' => 'task-ai-settlement:update', 'path' => '/admin/investment-settlement-times/*', 'method' => 'PUT', 'sort' => 3],
                ['name' => '结算时间点删除', 'slug' => 'task-ai-settlement:delete', 'path' => '/admin/investment-settlement-times/*', 'method' => 'DELETE', 'sort' => 4],
                ['name' => '结算周期配置', 'slug' => 'task-ai-settlement:cycle', 'path' => '/admin/investment-settlement-cycle', 'method' => 'PUT', 'sort' => 5],
                ['name' => '结算时间点状态', 'slug' => 'task-ai-settlement:toggle', 'path' => '/admin/investment-settlement-times/*/toggle', 'method' => 'PUT', 'sort' => 6],
            ],
        ];
    }

    public function __construct(private ?InvestmentSettlementTimeService $service = null)
    {
        $this->service = $service ?? new InvestmentSettlementTimeService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);

        return success($this->service->listTimes($page, $limit));
    }

    public function store(Request $request)
    {
        return success($this->service->createTime($request->post()), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        return success($this->service->updateTime($id, $request->post()), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->deleteTime($id);

        return success(null, '删除成功');
    }

    public function saveCycle(Request $request)
    {
        $hours = (float) $request->post('settlement_cycle', 0);
        $value = $this->service->saveSettlementCycle($hours);

        return success(['settlement_cycle' => $value], '保存成功');
    }

    public function toggle(Request $request, int $id)
    {
        return success($this->service->toggleStatus($id), '状态已更新');
    }
}
