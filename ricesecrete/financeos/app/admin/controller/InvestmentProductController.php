<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\InvestmentProductService;
use support\Request;

class InvestmentProductController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => ['name' => '任务中心', 'slug' => 'task', 'path' => '/task', 'icon' => 'list', 'sort' => 45],
            'menu' => [
                'name' => 'AI托管配置', 'slug' => 'task-ai-config', 'path' => '/task/ai-config',
                'icon' => 'cpu', 'component' => 'views/task/ai-config/index', 'sort' => 6,
            ],
            'apis' => [
                ['name' => 'AI产品列表', 'slug' => 'task-ai-config:list', 'path' => '/admin/investment-products', 'method' => 'GET', 'sort' => 1],
                ['name' => 'AI产品详情', 'slug' => 'task-ai-config:show', 'path' => '/admin/investment-products/*', 'method' => 'GET', 'sort' => 2],
                ['name' => 'AI产品创建', 'slug' => 'task-ai-config:create', 'path' => '/admin/investment-products', 'method' => 'POST', 'sort' => 3],
                ['name' => 'AI产品更新', 'slug' => 'task-ai-config:update', 'path' => '/admin/investment-products/*', 'method' => 'PUT', 'sort' => 4],
                ['name' => 'AI产品切换', 'slug' => 'task-ai-config:toggle', 'path' => '/admin/investment-products/*/toggle', 'method' => 'PUT', 'sort' => 5],
                ['name' => '长周期购买次数', 'slug' => 'task-ai-config:long-order-limit', 'path' => '/admin/investment-products/long-order-limit', 'method' => 'PUT', 'sort' => 6],
            ],
        ];
    }

    protected InvestmentProductService $service;

    public function __construct(?InvestmentProductService $service = null)
    {
        $this->service = $service ?? new InvestmentProductService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['keyword', 'status']);
        return success($this->service->list($page, $limit, $filters));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'product_name', 'cycle', 'day_num', 'profit', 'profit_type', 'level_id', 'give_amount',
            'give_account_type', 'min_balance', 'max_balance', 'total_quota',
            'order_limit', 'commission_rates', 'started_at', 'ended_at', 'rebated_at',
            'rebate_type', 'is_shortly_rebate', 'sales_status', 'description', 'income_text', 'sort', 'status',
        ]);
        return success($this->service->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only([
            'product_name', 'cycle', 'day_num', 'profit', 'profit_type', 'level_id', 'give_amount',
            'give_account_type', 'min_balance', 'max_balance', 'total_quota',
            'order_limit', 'commission_rates', 'started_at', 'ended_at', 'rebated_at',
            'rebate_type', 'is_shortly_rebate', 'sales_status', 'description', 'income_text', 'sort', 'status',
        ]);
        return success($this->service->update($id, $data), '更新成功');
    }

    public function toggle(Request $request, int $id)
    {
        $field = (string) $request->post('field', 'status');
        $value = (int) $request->post('value', 0);
        return success($this->service->toggle($id, $field, $value), '操作成功');
    }

    public function saveLongOrderLimit(Request $request)
    {
        $limit = (int) ($request->post('long_order_limit', $request->input('long_order_limit', 5)));

        return success([
            'long_order_limit' => $this->service->saveLongOrderLimit($limit),
        ], '保存成功');
    }
}
