<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\PayPlatformService;
use support\Request;

class PayPlatformController extends BaseController
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
                'name' => '充值配置',
                'slug' => 'finance-platform',
                'path' => '/finance/platform',
                'icon' => 'setting',
                'component' => 'views/finance/platform/index',
                'sort' => 1,
            ],
            'apis' => [
                ['name' => '通道列表', 'slug' => 'finance-platform:list', 'path' => '/admin/pay-platforms', 'method' => 'GET', 'sort' => 1],
                ['name' => '通道选项', 'slug' => 'finance-platform:options', 'path' => '/admin/pay-platforms/options', 'method' => 'GET', 'sort' => 2],
                ['name' => '通道详情', 'slug' => 'finance-platform:show', 'path' => '/admin/pay-platforms/*', 'method' => 'GET', 'sort' => 3],
                ['name' => '创建通道', 'slug' => 'finance-platform:create', 'path' => '/admin/pay-platforms', 'method' => 'POST', 'sort' => 4],
                ['name' => '更新通道', 'slug' => 'finance-platform:update', 'path' => '/admin/pay-platforms/*', 'method' => 'PUT', 'sort' => 5],
                ['name' => '删除通道', 'slug' => 'finance-platform:delete', 'path' => '/admin/pay-platforms/*', 'method' => 'DELETE', 'sort' => 6],
                ['name' => '通道开关', 'slug' => 'finance-platform:toggle', 'path' => '/admin/pay-platforms/*/toggle', 'method' => 'PUT', 'sort' => 7],
            ],
        ];
    }

    protected PayPlatformService $service;

    public function __construct(?PayPlatformService $service = null)
    {
        $this->service = $service ?? new PayPlatformService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['keyword', 'status', 'currency_type']);
        return success($this->service->list($page, $limit, $filters));
    }

    public function options(Request $request)
    {
        $rechargeOnly = (bool) $request->get('recharge_only', false);
        $withdrawOnly = (bool) $request->get('withdraw_only', false);
        return success($this->service->options($rechargeOnly, $withdrawOnly));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'currency_type', 'currency_code', 'unit', 'name', 'show_name', 'image_url',
            'min_recharge_amount', 'min_withdraw_amount', 'max_withdraw_amount', 'fast_amount',
            'recharge_is_show', 'withdraw_is_show', 'withdraw_money_type',
            'config', 'status', 'is_first_charge', 'sort',
            'exchange_rate', 'instruction', 'remark', 'coin',
        ]);
        return success($this->service->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only([
            'currency_type', 'currency_code', 'unit', 'name', 'show_name', 'image_url',
            'min_recharge_amount', 'min_withdraw_amount', 'max_withdraw_amount', 'fast_amount',
            'recharge_is_show', 'withdraw_is_show', 'withdraw_money_type',
            'config', 'status', 'is_first_charge', 'sort',
            'exchange_rate', 'instruction', 'remark', 'coin',
        ]);
        return success($this->service->update($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success(null, '删除成功');
    }

    public function toggle(Request $request, int $id)
    {
        $field = (string) $request->post('field', 'status');
        $value = (int) $request->post('value', 0);
        return success($this->service->toggle($id, $field, $value), '更新成功');
    }
}
