<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\RechargeService;
use support\Request;

class RechargeController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '账单管理',
                'slug' => 'finance',
                'path' => '/finance',
                'icon' => 'money',
                'sort' => 40,
            ],
            'menu' => [
                'name' => '充值订单',
                'slug' => 'finance-recharge',
                'path' => '/finance/recharge',
                'icon' => 'coin',
                'component' => 'views/finance/recharge/index',
                'sort' => 2,
            ],
            'apis' => [
                ['name' => '充值列表', 'slug' => 'finance-recharge:list', 'path' => '/admin/recharge-orders', 'method' => 'GET', 'sort' => 1],
                ['name' => '充值详情', 'slug' => 'finance-recharge:show', 'path' => '/admin/recharge-orders/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '充值审核', 'slug' => 'finance-recharge:audit', 'path' => '/admin/recharge-orders/*/audit', 'method' => 'PUT', 'sort' => 3],
                ['name' => '手动补单', 'slug' => 'finance-recharge:manual', 'path' => '/admin/recharge-orders/manual', 'method' => 'POST', 'sort' => 4],
            ],
        ];
    }

    protected RechargeService $service;

    public function __construct(?RechargeService $service = null)
    {
        $this->service = $service ?? new RechargeService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only([
            'keyword', 'user_id', 'order_no', 'status', 'type', 'is_virtual', 'pay_platform_id', 'start_date', 'end_date',
        ]);
        return success($this->service->list($page, $limit, $filters));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function audit(Request $request, int $id)
    {
        $status = (int) $request->post('status', 0);
        $remark = (string) $request->post('remark', '');
        $adminId = $this->adminId($request);
        return success($this->service->audit($id, $status, $remark, $adminId), '审核完成');
    }

    public function manual(Request $request)
    {
        $data = $request->only(['user_id', 'money', 'pay_platform_id', 'remark']);
        $adminId = $this->adminId($request);
        $ip = (string) $request->getRealIp();
        return success($this->service->manualCreate($data, $adminId, $ip), '补单成功');
    }
}
