<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\VideoService;
use support\Request;

class VideoController extends BaseController
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
                'name' => '视频管理',
                'slug' => 'video-menu',
                'path' => '/content/video',
                'icon' => 'edit',
                'component' => 'views/content/video/index',
                'sort' => 4,
            ],
            'apis' => [
                ['name' => '视频列表', 'slug' => 'video:list', 'path' => '/admin/videos', 'method' => 'GET', 'sort' => 1],
                ['name' => '视频分类', 'slug' => 'video:cates', 'path' => '/admin/videos/cates', 'method' => 'GET', 'sort' => 2],
                ['name' => '视频详情', 'slug' => 'video:show', 'path' => '/admin/videos/*', 'method' => 'GET', 'sort' => 3],
                ['name' => '创建视频', 'slug' => 'video:create', 'path' => '/admin/videos', 'method' => 'POST', 'sort' => 4],
                ['name' => '更新视频', 'slug' => 'video:update', 'path' => '/admin/videos/*', 'method' => 'PUT', 'sort' => 5],
                ['name' => '删除视频', 'slug' => 'video:delete', 'path' => '/admin/videos/*', 'method' => 'DELETE', 'sort' => 6],
            ],
        ];
    }

    public function __construct(private ?VideoService $service = null)
    {
        $this->service = $service ?? new VideoService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        return success($this->service->list($page, $limit, $request->only(['keyword', 'cate', 'status', 'lang'])));
    }

    public function cates(Request $request)
    {
        return success($this->service->cateOptions());
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function store(Request $request)
    {
        try {
            return success($this->service->create($request->post()), '创建成功');
        } catch (\app\exception\BusinessException $e) {
            return fail($e->getCode(), $e->getMessage());
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            return success($this->service->update($id, $request->post()), '更新成功');
        } catch (\app\exception\BusinessException $e) {
            return fail($e->getCode(), $e->getMessage());
        }
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $this->service->delete($id);
            return success(null, '删除成功');
        } catch (\app\exception\BusinessException $e) {
            return fail($e->getCode(), $e->getMessage());
        }
    }
}
