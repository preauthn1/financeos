<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\AdProductOrderService;
use support\Request;

class AdProductOrderController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '任务管理',
                'slug' => 'task',
                'path' => '/task',
                'icon' => 'list',
                'sort' => 45,
            ],
            'menu' => [
                'name' => '广告位购买记录',
                'slug' => 'task-ad-order',
                'path' => '/task/ad-order',
                'icon' => 'shopping-cart',
                'component' => 'views/task/ad-order/index',
                'sort' => 1,
            ],
            'apis' => [
                ['name' => '广告订单列表', 'slug' => 'task-ad-order:list', 'path' => '/admin/ad-product-orders', 'method' => 'GET', 'sort' => 1],
                ['name' => '广告订单批次详情', 'slug' => 'task-ad-order:session', 'path' => '/admin/ad-product-orders/session', 'method' => 'GET', 'sort' => 2],
                ['name' => '广告订单代理汇总', 'slug' => 'task-ad-order:agent-stats', 'path' => '/admin/ad-product-orders/agent-stats', 'method' => 'GET', 'sort' => 3],
                ['name' => '用户广告位记录', 'slug' => 'task-ad-order:user-records', 'path' => '/admin/ad-product-orders/user-records', 'method' => 'GET', 'sort' => 4],
                ['name' => '代理广告位记录', 'slug' => 'task-ad-order:agent-records', 'path' => '/admin/ad-product-orders/agent-records', 'method' => 'GET', 'sort' => 5],
                ['name' => '广告订单详情', 'slug' => 'task-ad-order:show', 'path' => '/admin/ad-product-orders/*', 'method' => 'GET', 'sort' => 6],
                ['name' => '结束广告订单批次', 'slug' => 'task-ad-order:end', 'path' => '/admin/ad-product-orders/end-session', 'method' => 'POST', 'sort' => 7],
                ['name' => '编辑广告订单', 'slug' => 'task-ad-order:update', 'path' => '/admin/ad-product-orders/*', 'method' => 'PUT', 'sort' => 8],
            ],
        ];
    }

    protected AdProductOrderService $service;

    public function __construct(?AdProductOrderService $service = null)
    {
        $this->service = $service ?? new AdProductOrderService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only([
            'keyword', 'user_id', 'order_no', 'status', 'session_no', 'level_id', 'agent_id',
            'start_date', 'end_date', 'start_time', 'end_time', 'is_virtual',
            'match_type', 'include_pending',
        ]);

        return success($this->service->list($page, $limit, $filters));
    }

    public function session(Request $request)
    {
        $userId = (int) $request->get('user_id', 0);
        $sessionNo = (string) $request->get('session_no', '');
        if ($userId <= 0 || $sessionNo === '') {
            return fail(42201, '参数错误');
        }

        return success($this->service->sessionDetail($userId, $sessionNo));
    }

    public function agentStats(Request $request)
    {
        $filters = $request->only([
            'keyword', 'user_id', 'order_no', 'status', 'session_no', 'level_id', 'agent_id',
            'start_date', 'end_date', 'start_time', 'end_time', 'is_virtual', 'match_type',
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
            'order_no', 'status', 'session_no', 'level_id',
            'start_date', 'end_date', 'start_time', 'end_time', 'match_type', 'include_pending',
        ]);

        return success($this->service->userRecords($userId, $filters));
    }

    public function agentRecords(Request $request)
    {
        $agentId = (int) $request->get('agent_id', 0);
        if ($agentId <= 0) {
            return fail(42201, '参数错误');
        }
        $filters = $request->only([
            'keyword', 'order_no', 'status', 'session_no', 'level_id',
            'start_date', 'end_date', 'start_time', 'end_time', 'match_type', 'include_pending',
        ]);

        return success($this->service->agentRecords($agentId, $filters));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only(['invest_amount', 'profit', 'cycle', 'invest_period_type']);

        return success($this->service->update($id, $data), '更新成功');
    }

    public function endSession(Request $request)
    {
        $userId = (int) $request->post('user_id', 0);
        $sessionNo = (string) $request->post('session_no', '');
        if ($userId <= 0 || $sessionNo === '') {
            return fail(42201, '参数错误');
        }

        return success($this->service->endSession($userId, $sessionNo), '已结束订单');
    }
}
