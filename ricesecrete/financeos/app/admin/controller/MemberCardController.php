<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use support\Request;
use app\service\MemberCardService;

class MemberCardController extends BaseController
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
                'name' => '银行卡设置',
                'slug' => 'member-card',
                'path' => '/member/card',
                'icon' => 'wallet',
                'component' => 'views/member/card/index',
                'sort' => 5,
            ],
            'apis' => [
                ['name' => '银行卡列表', 'slug' => 'member-card:list', 'path' => '/admin/member-cards', 'method' => 'GET', 'sort' => 1],
                ['name' => '创建银行卡', 'slug' => 'member-card:create', 'path' => '/admin/member-cards', 'method' => 'POST', 'sort' => 2],
                ['name' => '更新银行卡', 'slug' => 'member-card:update', 'path' => '/admin/member-cards/*', 'method' => 'PUT', 'sort' => 3],
                ['name' => '删除银行卡', 'slug' => 'member-card:delete', 'path' => '/admin/member-cards/*', 'method' => 'DELETE', 'sort' => 4],
            ],
        ];
    }

    protected MemberCardService $service;

    public function __construct(?MemberCardService $service = null)
    {
        $this->service = $service ?? new MemberCardService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['keyword', 'user_id', 'type']);
        return success($this->service->list($page, $limit, $filters));
    }

    public function store(Request $request)
    {
        $data = $request->only(['user_id', 'type', 'bank_name', 'bank_code', 'card_number', 'card_holder', 'bank_branch', 'bank_address', 'is_default', 'status']);
        return success($this->service->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only(['bank_name', 'bank_code', 'card_number', 'card_holder', 'bank_branch', 'bank_address', 'is_default', 'status']);
        return success($this->service->update($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success(null, '删除成功');
    }
}
