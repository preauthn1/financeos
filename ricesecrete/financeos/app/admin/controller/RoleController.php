<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\controller\concerns\DefinesAdminMenu;
use support\Request;
use app\service\RoleService;

class RoleController extends BaseController
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
                'name' => '角色管理',
                'slug' => 'role-menu',
                'path' => '/auth/role',
                'icon' => 'peoples',
                'component' => 'views/system/role/index',
                'sort' => 2,
            ],
            'apis' => [
                ['name' => '角色列表', 'slug' => 'role:list', 'path' => '/admin/roles', 'method' => 'GET', 'sort' => 1],
                ['name' => '创建角色', 'slug' => 'role:create', 'path' => '/admin/roles', 'method' => 'POST', 'sort' => 2],
                ['name' => '更新角色', 'slug' => 'role:update', 'path' => '/admin/roles/*', 'method' => 'PUT', 'sort' => 3],
                ['name' => '删除角色', 'slug' => 'role:delete', 'path' => '/admin/roles/*', 'method' => 'DELETE', 'sort' => 4],
                ['name' => '分配权限', 'slug' => 'role:assign', 'path' => '/admin/roles/*/permissions', 'method' => 'PUT', 'sort' => 5],
            ],
        ];
    }

    protected RoleService $roleService;

    public function __construct(?RoleService $roleService = null)
    {
        $this->roleService = $roleService ?? new RoleService();
    }

    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $filters = $request->only(['keyword']);
        return success($this->roleService->list($page, $limit, $filters));
    }

    public function store(Request $request)
    {
        $data = $request->only(['name', 'slug', 'description', 'data_scope']);
        if (empty($data['name']) || empty($data['slug'])) {
            return fail(42201, '名称和标识不能为空');
        }
        return success($this->roleService->create($data), '创建成功');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->only(['name', 'description', 'data_scope']);
        return success($this->roleService->update($id, $data), '更新成功');
    }

    public function destroy(Request $request, int $id)
    {
        $this->roleService->delete($id);
        return success(null, '删除成功');
    }

    public function permissions(Request $request, int $id)
    {
        return success($this->roleService->getPermissions($id));
    }

    public function assignPermissions(Request $request, int $id)
    {
        $permissionIds = $request->post('permission_ids', []);
        $this->roleService->assignPermissions($id, $permissionIds);
        return success(null, '权限分配成功');
    }
}
