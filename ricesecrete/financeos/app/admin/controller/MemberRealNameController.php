<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use support\Request;
use app\service\MemberRealNameService;

class MemberRealNameController extends BaseController
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
                'name' => '用户实名',
                'slug' => 'member-realname',
                'path' => '/member/realname',
                'icon' => 'id-card',
                'component' => 'views/member/realname/index',
                'sort' => 6,
            ],
            'apis' => [
                ['name' => '实名列表', 'slug' => 'member-realname:list', 'path' => '/admin/member-real-names', 'method' => 'GET', 'sort' => 1],
                ['name' => '创建实名', 'slug' => 'member-realname:create', 'path' => '/admin/member-real-names', 'method' => 'POST', 'sort' => 2],
                ['name' => '更新实名', 'slug' => 'member-realname:update', 'path' => '/admin/member-real-names/*', 'method' => 'PUT', 'sort' => 3],
                ['name' => '删除实名', 'slug' => 'member-realname:delete', 'path' => '/admin/member-real-names/*', 'method' => 'DELETE', 'sort' => 4],
                ['name' => '审核实名', 'slug' => 'member-realname:audit', 'path' => '/admin/member-real-names/*/audit', 'method' => 'PUT', 'sort' => 5],
            ],
        ];
    }

    protected MemberRealNameService $service;

    public function __construct(?MemberRealNameService $service = null)
    {
        $this->service = $service ?? new MemberRealNameService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['keyword', 'user_id', 'status']);
        return success($this->service->list($page, $limit, $filters));
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'user_id', 'real_name', 'id_card', 'id_card_front', 'id_card_back',
            'hand_hold_photo', 'document_type', 'remark',
        ]);
        $auth = $this->service->create($data, $this->adminId($request));
        $auth->load('user:id,username,nickname');
        return success($this->service->formatItem($auth), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only(['real_name', 'id_card', 'id_card_front', 'id_card_back', 'hand_hold_photo', 'document_type']);
        return success($this->service->update($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success(null, '删除成功');
    }

    public function audit(Request $request, int $id)
    {
        $status = (int) $request->post('status', 0);
        $remark = (string) $request->post('remark', '');
        $auditorId = $this->adminId($request);

        return success($this->service->audit($id, $status, $remark, $auditorId), '审核完成');
    }
}
