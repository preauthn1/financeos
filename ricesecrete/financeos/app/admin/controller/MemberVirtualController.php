<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use support\Request;
use app\service\MemberVirtualService;

class MemberVirtualController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'api_parent_menu_slug' => 'member-list',
            'apis' => [
                ['name' => '虚拟数据列表', 'slug' => 'member-virtual:list', 'path' => '/admin/member-virtuals', 'method' => 'GET', 'sort' => 1],
                ['name' => '更新虚拟数据', 'slug' => 'member-virtual:update', 'path' => '/admin/member-virtuals/*', 'method' => 'PUT', 'sort' => 2],
                ['name' => '手动调整', 'slug' => 'member-virtual:adjust', 'path' => '/admin/member-virtuals/adjust', 'method' => 'POST', 'sort' => 3],
                ['name' => '变更日志', 'slug' => 'member-virtual:logs', 'path' => '/admin/member-virtuals/logs', 'method' => 'GET', 'sort' => 4],
                ['name' => '删除日志', 'slug' => 'member-virtual:log-delete', 'path' => '/admin/member-virtuals/logs/*', 'method' => 'DELETE', 'sort' => 5],
            ],
        ];
    }

    protected MemberVirtualService $service;

    public function __construct(?MemberVirtualService $service = null)
    {
        $this->service = $service ?? new MemberVirtualService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['user_id']);
        return success($this->service->list($page, $limit, $filters));
    }

    public function update(Request $request, int $id)
    {
        return success($this->service->update($id, $request->post()), '更新成功');
    }

    public function adjust(Request $request)
    {
        $userId = (int) $request->post('user_id', 0);
        $field  = (string) $request->post('field', '');
        $amount = (string) $request->post('amount', '0');
        $remark = (string) $request->post('remark', '');

        if ($userId <= 0) return fail(42201, '用户ID不能为空');
        if ($field === '') return fail(42201, '字段不能为空');

        return success(
            $this->service->adjust($userId, $field, $amount, $remark, $this->adminId($request)),
            '调整成功'
        );
    }

    public function logs(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['user_id', 'start_date', 'end_date']);
        return success($this->service->logList($page, $limit, $filters));
    }

    public function destroyLog(Request $request, int $id)
    {
        $this->service->deleteVirtualLog($id);
        return success(null, '删除成功');
    }
}
