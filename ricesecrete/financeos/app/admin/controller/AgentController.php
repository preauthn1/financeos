<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\AgentService;
use support\Request;

class AgentController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '会员管理',
                'slug' => 'member',
                'path' => '/member',
                'icon' => 'people',
                'sort' => 30,
            ],
            'menu' => [
                'name' => '代理列表',
                'slug' => 'member-agent',
                'path' => '/member/agent',
                'icon' => 'avatar',
                'component' => 'views/member/agent/index',
                'sort' => 2,
            ],
            'apis' => [
                ['name' => '代理列表', 'slug' => 'member-agent:list', 'path' => '/admin/agents', 'method' => 'GET', 'sort' => 1],
                ['name' => '代理创建', 'slug' => 'member-agent:create', 'path' => '/admin/agents', 'method' => 'POST', 'sort' => 2],
                ['name' => '代理批量操作', 'slug' => 'member-agent:batch', 'path' => '/admin/agents/batch', 'method' => 'POST', 'sort' => 3],
                ['name' => '代理下级', 'slug' => 'member-agent:subordinates', 'path' => '/admin/agents/*/subordinates', 'method' => 'GET', 'sort' => 4],
            ],
        ];
    }

    protected AgentService $service;

    public function __construct(?AgentService $service = null)
    {
        $this->service = $service ?? new AgentService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only([
            'keyword', 'status', 'id', 'uid', 'username', 'user_name', 'invite_code',
            'time_type', 'start_time', 'end_time', 'startTime', 'endTime',
            'user_type', 'is_xuni', 'ip', 'level_id',
            'register_start', 'register_end',
        ]);

        return success($this->service->list($page, $limit, $filters));
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'username', 'password', 'parent_invite_code', 'lang', 'is_virtual', 'remark',
        ]);
        $agent = $this->service->create($data, $this->adminId($request));

        return success($agent, '创建成功');
    }

    public function batch(Request $request)
    {
        $ids = array_map('intval', (array) $request->post('ids', []));
        $action = (string) $request->post('action', '');

        if ($ids === []) {
            return fail(42201, '请选择代理');
        }

        return match ($action) {
            'enable' => success(
                ['count' => $this->service->batchUpdateStatus($ids, 1)],
                '已启用代理'
            ),
            'disable' => success(
                ['count' => $this->service->batchUpdateStatus($ids, 0)],
                '已禁用代理'
            ),
            default => fail(42201, "不支持的操作: {$action}"),
        };
    }

    public function subordinates(Request $request, int $id)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['level', 'uid', 'username', 'invite_code']);

        return success($this->service->subordinates($id, $page, $limit, $filters));
    }
}
