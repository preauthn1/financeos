<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\BannerService;
use support\Request;

class BannerController extends BaseController
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
                'name' => '轮播图管理',
                'slug' => 'banner-menu',
                'path' => '/content/banner',
                'icon' => 'picture',
                'component' => 'views/content/banner/index',
                'sort' => 3,
            ],
            'apis' => [
                ['name' => '轮播图列表', 'slug' => 'banner:list', 'path' => '/admin/banners', 'method' => 'GET', 'sort' => 1],
                ['name' => '轮播图详情', 'slug' => 'banner:show', 'path' => '/admin/banners/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '创建轮播图', 'slug' => 'banner:create', 'path' => '/admin/banners', 'method' => 'POST', 'sort' => 3],
                ['name' => '更新轮播图', 'slug' => 'banner:update', 'path' => '/admin/banners/*', 'method' => 'PUT', 'sort' => 4],
                ['name' => '删除轮播图', 'slug' => 'banner:delete', 'path' => '/admin/banners/*', 'method' => 'DELETE', 'sort' => 5],
            ],
        ];
    }

    protected BannerService $service;

    public function __construct(?BannerService $service = null)
    {
        $this->service = $service ?? new BannerService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['keyword', 'region', 'status']);
        return success($this->service->list($page, $limit, $filters));
    }

    public function show(Request $request, int $id)
    {
        return success($this->service->show($id));
    }

    public function store(Request $request)
    {
        $data = $request->only(['lang', 'name', 'link', 'image_url', 'status', 'sort', 'region']);
        if (empty($data['image_url'])) {
            return fail(42201, '请上传轮播图');
        }
        return success($this->service->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only(['lang', 'name', 'link', 'image_url', 'status', 'sort', 'region']);
        return success($this->service->update($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success(null, '删除成功');
    }
}
