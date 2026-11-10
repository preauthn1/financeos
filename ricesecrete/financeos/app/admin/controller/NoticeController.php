<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\NoticeService;
use support\Request;

class NoticeController extends BaseController
{
    use DefinesAdminMenu;

    public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '内容管理',
                'slug' => 'content',
                'path' => '/content',
                'icon' => 'document',
                'sort' => 10,
            ],
            'menu' => [
                'name' => '公告管理',
                'slug' => 'notice-menu',
                'path' => '/content/notice',
                'icon' => 'bell',
                'component' => 'views/content/notice/index',
                'sort' => 4,
            ],
            'apis' => [
                ['name' => '公告列表', 'slug' => 'notice:list', 'path' => '/admin/notices', 'method' => 'GET', 'sort' => 1],
                ['name' => '公告详情', 'slug' => 'notice:show', 'path' => '/admin/notices/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '创建公告', 'slug' => 'notice:create', 'path' => '/admin/notices', 'method' => 'POST', 'sort' => 3],
                ['name' => '更新公告', 'slug' => 'notice:update', 'path' => '/admin/notices/*', 'method' => 'PUT', 'sort' => 4],
                ['name' => '删除公告', 'slug' => 'notice:delete', 'path' => '/admin/notices/*', 'method' => 'DELETE', 'sort' => 5],
            ],
        ];
    }

    protected NoticeService $service;

    public function __construct(?NoticeService $service = null)
    {
        $this->service = $service ?? new NoticeService();
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
        $data = $request->only(['user_id', 'lang', 'title', 'type', 'content', 'status', 'translations']);
        if (empty($data['content'])) {
            return fail(42201, '公告内容不能为空');
        }
        return success($this->service->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only(['user_id', 'lang', 'title', 'type', 'content', 'status', 'translations']);
        return success($this->service->update($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success(null, '删除成功');
    }
}
