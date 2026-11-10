<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use app\service\CustomerLinkService;
use support\Request;

class CustomerServiceController extends BaseController
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
                'name' => '客服管理',
                'slug' => 'customer-service-menu',
                'path' => '/content/customer-service',
                'icon' => 'service',
                'component' => 'views/content/customer-service/index',
                'sort' => 5,
            ],
            'apis' => [
                ['name' => '客服列表', 'slug' => 'customer-service:list', 'path' => '/admin/customer-services', 'method' => 'GET', 'sort' => 1],
                ['name' => '客服详情', 'slug' => 'customer-service:show', 'path' => '/admin/customer-services/*', 'method' => 'GET', 'sort' => 2],
                ['name' => '创建客服', 'slug' => 'customer-service:create', 'path' => '/admin/customer-services', 'method' => 'POST', 'sort' => 3],
                ['name' => '更新客服', 'slug' => 'customer-service:update', 'path' => '/admin/customer-services/*', 'method' => 'PUT', 'sort' => 4],
                ['name' => '删除客服', 'slug' => 'customer-service:delete', 'path' => '/admin/customer-services/*', 'method' => 'DELETE', 'sort' => 5],
            ],
        ];
    }

    protected CustomerLinkService $service;

    public function __construct(?CustomerLinkService $service = null)
    {
        $this->service = $service ?? new CustomerLinkService();
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
        $data = $request->only(['image_url', 'link', 'name', 'sort', 'status']);
        if (empty($data['name']) || empty($data['link'])) {
            return fail(42201, '名称和链接不能为空');
        }
        return success($this->service->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only(['image_url', 'link', 'name', 'sort', 'status']);
        return success($this->service->update($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->service->delete($id);
        return success(null, '删除成功');
    }
}
