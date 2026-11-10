<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\LevelTeamService;
use support\Request;

class LevelTeamController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'api_parent_menu_slug' => 'member-list',
            'apis' => [
                ['name' => '团队配置列表', 'slug' => 'task-level-team:list', 'path' => '/admin/level-teams', 'method' => 'GET', 'sort' => 1],
                ['name' => '用户团队配置', 'slug' => 'task-level-team:for-user', 'path' => '/admin/level-teams/for-user/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '团队配置创建', 'slug' => 'task-level-team:create', 'path' => '/admin/level-teams', 'method' => 'POST', 'sort' => 3],
                ['name' => '团队配置更新', 'slug' => 'task-level-team:update', 'path' => '/admin/level-teams/*', 'method' => 'PUT', 'sort' => 4],
                ['name' => '团队配置开关', 'slug' => 'task-level-team:toggle', 'path' => '/admin/level-teams/*/toggle', 'method' => 'PUT', 'sort' => 5],
                ['name' => '团队配置删除', 'slug' => 'task-level-team:delete', 'path' => '/admin/level-teams/*', 'method' => 'DELETE', 'sort' => 6],
            ],
        ];
    }

    public function __construct(private ?LevelTeamService $service = null)
    {
        $this->service = $service ?? new LevelTeamService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->list($page, $limit, $request->only(['user_id', 'level_id', 'status'])));
    }

    public function forUser(Request $request, int $userId)
    {
        return success($this->service->listForUser($userId));
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'user_id', 'level_id', 'invest_num', 'invest_rate', 'invest_max_rate', 'active_rate',
            'invest_profit', 'invest_day', 'invest_period_type', 'invest_password', 'status', 'subordinate',
        ]);
        return success($this->service->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only([
            'invest_num', 'invest_rate', 'invest_max_rate', 'active_rate', 'invest_profit',
            'invest_day', 'invest_period_type', 'invest_password', 'status', 'subordinate',
        ]);
        return success($this->service->update($id, $data), '更新成功');
    }

    public function toggle(Request $request, int $id)
    {
        $field = (string) $request->post('field', '');
        $value = (int) $request->post('value', 0);
        return success($this->service->toggle($id, $field, $value), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success(null, '删除成功');
    }
}
