<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\support\ErrorCode;
use app\model\sys\AdminModel;
use app\model\sys\RoleModel;

class AdminService
{
    protected PermissionService $permissionService;
    protected AuthService $authService;
    protected AdminGoogleAuthService $googleAuthService;

    public function __construct(
        ?PermissionService $permissionService = null,
        ?AuthService $authService = null,
        ?AdminGoogleAuthService $googleAuthService = null
    ) {
        $this->permissionService = $permissionService ?? new PermissionService();
        $this->authService = $authService ?? new AuthService();
        $this->googleAuthService = $googleAuthService ?? new AdminGoogleAuthService();
    }
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = AdminModel::query();

        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                    ->orWhere('nickname', 'like', "%{$keyword}%");
            });
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $total = $query->count();
        $items = $query->with('roles:id,name')
            ->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function (AdminModel $admin) {
                $data = $admin->toArray();
                $data['google_auth_bound'] = $this->googleAuthService->isBound($admin);

                return $data;
            });

        return ['total' => $total, 'items' => $items];
    }

    public function create(array $data): AdminModel
    {
        if (AdminModel::where('username', $data['username'])->exists()) {
            throw new BusinessException(ErrorCode::USERNAME_EXISTS);
        }

        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        $roleIds = $data['role_ids'] ?? [];
        unset($data['role_ids']);

        $admin = AdminModel::create($data);
        if ($roleIds) {
            $admin->roles()->sync($roleIds);
            $this->permissionService->clearCache($admin->id);
        }
        return $admin->load('roles:id,name');
    }

    public function update(int $id, array $data): AdminModel
    {
        $admin = AdminModel::find($id);
        if (!$admin) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '管理员不存在');
        }

        $roleIds = $data['role_ids'] ?? null;
        unset($data['role_ids'], $data['password'], $data['username']);

        $admin->update($data);
        if ($roleIds !== null) {
            $admin->roles()->sync($roleIds);
            $this->permissionService->clearCache($admin->id);
        }
        return $admin->load('roles:id,name');
    }

    public function delete(int $id): void
    {
        $admin = AdminModel::find($id);
        if (!$admin) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '管理员不存在');
        }

        $isSuperAdmin = $admin->roles()->where('slug', 'super_admin')->exists();
        if ($isSuperAdmin) {
            $superCount = AdminModel::whereHas('roles', fn($q) => $q->where('slug', 'super_admin'))->count();
            if ($superCount <= 1) {
                throw new BusinessException(ErrorCode::CANNOT_DELETE_LAST_SUPER_ADMIN);
            }
            throw new BusinessException(ErrorCode::CANNOT_DELETE_SUPER_ADMIN);
        }

        $admin->delete();
        $this->permissionService->clearCache($id);
        $this->authService->revokeAdminSessions($id);
    }

    public function updatePassword(int $id, string $oldPassword, string $newPassword, ?int $operatorId = null): void
    {
        $admin = AdminModel::find($id);
        if (!$admin) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '管理员不存在');
        }

        // 改自己的密码：校验原密码；重置他人密码：不校验原密码
        if ($operatorId === null || $operatorId === $id) {
            if (!password_verify($oldPassword, $admin->password)) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '原密码错误');
            }
        }

        $admin->update(['password' => password_hash($newPassword, PASSWORD_BCRYPT)]);

        // 重置他人密码后踢掉其会话，强制重新登录
        if ($operatorId !== null && $operatorId !== $id) {
            $this->authService->revokeAdminSessions($id);
        }
    }

    public function clearGoogleAuth(int $id): void
    {
        $this->googleAuthService->clear($id);
        $this->authService->revokeAdminSessions($id);
    }
}
