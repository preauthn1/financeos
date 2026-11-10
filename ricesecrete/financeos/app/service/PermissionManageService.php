<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\support\ErrorCode;
use app\model\sys\PermissionModel;

class PermissionManageService
{
    protected PermissionService $permissionService;

    public function __construct(?PermissionService $permissionService = null)
    {
        $this->permissionService = $permissionService ?? new PermissionService();
    }

    public function list(?string $type = null): array
    {
        return $this->permissionService->getTree($type);
    }

    public function create(array $data): PermissionModel
    {
        if (PermissionModel::where('slug', $data['slug'])->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '权限标识已存在');
        }
        $permission = PermissionModel::create($data);
        $this->permissionService->clearCacheForAll();
        return $permission;
    }

    public function update(int $id, array $data): PermissionModel
    {
        $permission = PermissionModel::find($id);
        if (!$permission) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '权限不存在');
        }

        unset($data['slug']);
        $permission->update($data);
        $this->permissionService->clearCacheForAll();
        return $permission;
    }

    public function delete(int $id): void
    {
        $permission = PermissionModel::find($id);
        if (!$permission) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '权限不存在');
        }

        if (PermissionModel::where('parent_id', $id)->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '存在子权限，无法删除');
        }

        $permission->delete();
        $this->permissionService->clearCacheForAll();
    }
}
