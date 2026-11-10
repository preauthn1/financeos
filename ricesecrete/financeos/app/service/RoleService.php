<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\sys\AdminModel;
use app\model\sys\RoleModel;
use app\support\ErrorCode;

class RoleService
{
    protected PermissionService $permissionService;

    public function __construct(?PermissionService $permissionService = null)
    {
        $this->permissionService = $permissionService ?? new PermissionService();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = RoleModel::query();

        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('slug', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items];
    }

    public function all(): array
    {
        return RoleModel::orderBy('id')->get()->toArray();
    }

    public function create(array $data): RoleModel
    {
        if (RoleModel::where('slug', $data['slug'])->exists()) {
            throw new BusinessException(ErrorCode::ROLE_SLUG_EXISTS);
        }
        return RoleModel::create($data);
    }

    public function update(int $id, array $data): RoleModel
    {
        $role = RoleModel::find($id);
        if (!$role) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '角色不存在');
        }

        if ($role->slug === 'super_admin') {
            throw new BusinessException(ErrorCode::CANNOT_DELETE_SUPER_ADMIN, '不能修改超级管理员角色');
        }

        unset($data['slug']);
        $role->update($data);
        $this->permissionService->clearCacheForRole($id);
        return $role;
    }

    public function delete(int $id): void
    {
        $role = RoleModel::find($id);
        if (!$role) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '角色不存在');
        }

        if ($role->slug === 'super_admin') {
            throw new BusinessException(ErrorCode::CANNOT_DELETE_SUPER_ADMIN, '不能删除超级管理员角色');
        }

        $role->delete();
        $this->permissionService->clearCacheForRole($id);
    }

    public function getPermissions(int $id): array
    {
        $role = RoleModel::find($id);
        if (!$role) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '角色不存在');
        }
        return ['permission_ids' => $role->permissions()->pluck('sy_permissions.id')->toArray()];
    }

    public function assignPermissions(int $id, array $permissionIds): void
    {
        $role = RoleModel::find($id);
        if (!$role) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '角色不存在');
        }

        if ($role->slug === 'super_admin') {
            throw new BusinessException(ErrorCode::CANNOT_DELETE_SUPER_ADMIN, '不能修改超级管理员角色权限');
        }

        $role->permissions()->sync($permissionIds);

        $adminIds = AdminModel::whereHas('roles', static function ($query) use ($id) {
            $query->where('sy_roles.id', $id);
        })->pluck('id');

        foreach ($adminIds as $adminId) {
            $this->permissionService->clearCache((int) $adminId);
        }
    }
}
