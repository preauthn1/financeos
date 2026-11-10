<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\TeamOperationCenterService;
use support\Request;

class TeamOperationCenterController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '运营中心', 'slug' => 'ops', 'path' => '/ops', 'icon' => 'office-building', 'sort' => 52,
            ],
            'menu' => [
                'name' => '申请数据', 'slug' => 'ops-apply', 'path' => '/ops/apply',
                'icon' => 'edit', 'component' => 'views/ops/apply/index', 'sort' => 1,
            ],
            'apis' => [
                ['name' => '申请列表', 'slug' => 'ops-apply:list', 'path' => '/admin/operation-centers', 'method' => 'GET', 'sort' => 1],
                ['name' => '申请状态变更', 'slug' => 'ops-apply:status', 'path' => '/admin/operation-centers/*/status', 'method' => 'PUT', 'sort' => 2],
                ['name' => '佣金配置读取', 'slug' => 'ops-apply:brokerage-show', 'path' => '/admin/operation-centers/brokerage-config/*', 'method' => 'GET', 'sort' => 3],
                ['name' => '佣金配置保存', 'slug' => 'ops-apply:brokerage-save', 'path' => '/admin/operation-centers/brokerage-config/*', 'method' => 'PUT', 'sort' => 4],
            ],
        ];
    }

    protected TeamOperationCenterService $service;

    public function __construct(?TeamOperationCenterService $service = null)
    {
        $this->service = $service ?? new TeamOperationCenterService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['user_id', 'submit_user_id', 'status', 'type', 'start_date', 'end_date']);

        return success($this->service->list($page, $limit, $filters));
    }

    public function changeStatus(Request $request, int $id)
    {
        $status = (int) $request->post('status', 0);
        $item = $this->service->changeStatus($id, $status);

        return success($this->service->formatItem($item), '操作成功');
    }

    /** @deprecated */
    public function audit(Request $request, int $id)
    {
        return $this->changeStatus($request, $id);
    }

    public function brokerageConfig(Request $request, string $key)
    {
        return success($this->service->getBrokerageConfig($key));
    }

    public function saveBrokerageConfig(Request $request, string $key)
    {
        $items = $request->post('items', []);
        if (!is_array($items)) {
            $items = [];
        }

        return success($this->service->saveBrokerageConfig($key, $items), '保存成功');
    }
}
