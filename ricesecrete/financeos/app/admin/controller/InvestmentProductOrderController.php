<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\InvestmentProductOrderService;
use support\Request;

class InvestmentProductOrderController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '任务管理', 'slug' => 'task', 'path' => '/task', 'icon' => 'list', 'sort' => 45,
            ],
            'menu' => [
                'name' => 'AI托管订单', 'slug' => 'task-ai-trust', 'path' => '/task/ai-trust',
                'icon' => 'cpu', 'component' => 'views/task/ai-trust/index', 'sort' => 2,
            ],
            'apis' => [
                ['name' => 'AI订单列表', 'slug' => 'task-ai-trust:list', 'path' => '/admin/investment-orders', 'method' => 'GET', 'sort' => 1],
                ['name' => 'AI订单代理汇总', 'slug' => 'task-ai-trust:agent-stats', 'path' => '/admin/investment-orders/agent-stats', 'method' => 'GET', 'sort' => 2],
                ['name' => '用户AI托管记录', 'slug' => 'task-ai-trust:user-records', 'path' => '/admin/investment-orders/user-records', 'method' => 'GET', 'sort' => 3],
                ['name' => 'AI订单详情', 'slug' => 'task-ai-trust:show', 'path' => '/admin/investment-orders/*', 'method' => 'GET', 'sort' => 4],
                ['name' => 'AI订单操作', 'slug' => 'task-ai-trust:action', 'path' => '/admin/investment-orders/*/action', 'method' => 'POST', 'sort' => 5],
            ],
        ];
    }

    protected InvestmentProductOrderService $service;

    public function __construct(?InvestmentProductOrderService $service = null)
    {
        $this->service = $service ?? new InvestmentProductOrderService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only([
            'user_id', 'order_no', 'username', 'keyword', 'status', 'agent_id', 'is_virtual',
            'start_date', 'end_date', 'start_time', 'end_time',
        ]);

        return success($this->service->list($page, $limit, $filters));
    }

    public function agentStats(Request $request)
    {
        $filters = $request->only([
            'user_id', 'order_no', 'username', 'keyword', 'status', 'agent_id', 'is_virtual',
            'start_date', 'end_date', 'start_time', 'end_time',
        ]);

        return success($this->service->agentStats($filters));
    }

    public function userRecords(Request $request)
    {
        $userId = (int) $request->get('user_id', 0);
        if ($userId <= 0) {
            return fail(42201, '参数错误');
        }
        $filters = $request->only([
            'order_no', 'status', 'start_date', 'end_date', 'start_time', 'end_time',
        ]);

        return success($this->service->userRecords($userId, $filters));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function action(Request $request, int $id)
    {
        $action = (string) $request->post('action', '');
        $remark = (string) $request->post('remark', '');
        if ($action === '') {
            return fail(42201, '请选择操作类型');
        }

        return success($this->service->action($id, $action, $remark), '操作成功');
    }
}
