<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use support\Request;
use app\service\PermissionManageService;

class PermissionController extends BaseController
{
    use DefinesAdminMenu;

public static function menuConfig(): ?array
    {
        return [
            'group' => [
                'name' => '权限管理',
                'slug' => 'auth',
                'path' => '/auth',
                'icon' => 'lock',
                'sort' => 20,
            ],
            'menu' => [
                'name' => '权限管理',
                'slug' => 'permission-menu',
                'path' => '/auth/permission',
                'icon' => 'lock',
                'component' => 'views/system/permission/index',
                'sort' => 3,
            ],
            'apis' => [
                ['name' => '权限列表', 'slug' => 'permission:list', 'path' => '/admin/permissions', 'method' => 'GET', 'sort' => 1],
                ['name' => '创建权限', 'slug' => 'permission:create', 'path' => '/admin/permissions', 'method' => 'POST', 'sort' => 2],
                ['name' => '更新权限', 'slug' => 'permission:update', 'path' => '/admin/permissions/*', 'method' => 'PUT', 'sort' => 3],
                ['name' => '删除权限', 'slug' => 'permission:delete', 'path' => '/admin/permissions/*', 'method' => 'DELETE', 'sort' => 4],
            ],
        ];
    }

    protected PermissionManageService $permissionService;

    public function __construct(?PermissionManageService $permissionService = null)
    {
        $this->permissionService = $permissionService ?? new PermissionManageService();
    }

    public function index(Request $request)
    {
        $type = $request->get('type');
        return success($this->permissionService->list($type ?: null));
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'name', 'slug', 'type', 'parent_id', 'path',
            'method', 'icon', 'component', 'sort', 'hidden',
        ]);
        if (empty($data['name']) || empty($data['slug'])) {
            return fail(42201, '名称和标识不能为空');
        }
        $data['parent_id'] = (int) ($data['parent_id'] ?? 0);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['hidden'] = (int) ($data['hidden'] ?? 0);
        return success($this->permissionService->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only([
            'name', 'path', 'method', 'icon', 'component', 'sort', 'hidden',
        ]);
        return success($this->permissionService->update($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->permissionService->delete($id);
        return success(null, '删除成功');
    }
}
