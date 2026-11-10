<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use support\Request;
use app\service\MemberMessageService;

class MemberMessageController extends BaseController
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
                'name' => '站内信',
                'slug' => 'member-message',
                'path' => '/member/message',
                'icon' => 'message',
                'component' => 'views/member/message/index',
                'sort' => 11,
            ],
            'apis' => [
                ['name' => '站内信列表', 'slug' => 'member-message:list', 'path' => '/admin/member-messages', 'method' => 'GET', 'sort' => 1],
                ['name' => '发送站内信', 'slug' => 'member-message:send', 'path' => '/admin/member-messages', 'method' => 'POST', 'sort' => 2],
                ['name' => '群发站内信', 'slug' => 'member-message:broadcast', 'path' => '/admin/member-messages/broadcast', 'method' => 'POST', 'sort' => 3],
                ['name' => '删除站内信', 'slug' => 'member-message:delete', 'path' => '/admin/member-messages/*', 'method' => 'DELETE', 'sort' => 4],
            ],
        ];
    }

    protected MemberMessageService $service;

    public function __construct(?MemberMessageService $service = null)
    {
        $this->service = $service ?? new MemberMessageService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['keyword', 'user_id', 'type', 'is_popup']);
        return success($this->service->list($page, $limit, $filters));
    }

    public function store(Request $request)
    {
        $userId = (int) $request->post('user_id', 0);
        if ($userId <= 0) {
            return fail(42201, '用户ID不能为空');
        }

        $data = [
            'admin_id' => $this->adminId($request),
            'type'     => (int) $request->post('type', 1),
            'title'    => (string) $request->post('title', ''),
            'content'  => (string) $request->post('content', ''),
            'is_popup'   => (int) $request->post('is_popup', 0),
        ];

        if ($data['title'] === '' || $data['content'] === '') {
            return fail(42201, '标题和内容不能为空');
        }

        return success($this->service->sendToUser($userId, $data), '发送成功');
    }

    public function broadcast(Request $request)
    {
        $data = [
            'admin_id'   => $this->adminId($request),
            'type'       => (int) $request->post('type', 1),
            'title'      => (string) $request->post('title', ''),
            'content'    => (string) $request->post('content', ''),
            'is_popup'     => (int) $request->post('is_popup', 0),
            'level_id'   => $request->post('level_id'),
            'leader_id'  => $request->post('leader_id'),
            'is_virtual' => $request->post('is_virtual'),
        ];

        if ($data['title'] === '' || $data['content'] === '') {
            return fail(42201, '标题和内容不能为空');
        }

        $count = $this->service->broadcast($data);
        return success(['count' => $count], "已向 {$count} 个用户发送站内信");
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success(null, '删除成功');
    }
}
