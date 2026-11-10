<?php

declare(strict_types=1);

namespace app\service;

use app\model\sys\AdminModel;
use app\model\sys\PermissionModel;
use app\model\sys\RoleModel;
use Illuminate\Database\Eloquent\Builder;
use support\Redis;

class PermissionService
{
    public function checkApiPermission(int $adminId, string $path, string $method): bool
    {
        if (
            strtoupper($method) === 'GET'
            && preg_match('#^/admin/roles/\d+/permissions$#', $path) === 1
        ) {
            $slugs = $this->getAdminPermissions($adminId);
            if (in_array('role:assign', $slugs, true) || in_array('role:list', $slugs, true)) {
                return true;
            }
        }

        $permissions = $this->getAdminApiPermissions($adminId);

        foreach ($permissions as $perm) {
            if (strtoupper($perm['method']) !== strtoupper($method)) {
                continue;
            }
            if ($this->matchPath($perm['path'], $path)) {
                return true;
            }
        }
        return false;
    }

    public function checkButtonPermission(int $adminId, string $slug): bool
    {
        if ($this->isSuperAdmin($adminId)) {
            return true;
        }

        return in_array($slug, $this->getAdminPermissions($adminId), true);
    }

    public function assertButtonPermission(int $adminId, string $slug): void
    {
        if (!$this->checkButtonPermission($adminId, $slug)) {
            throw new \app\exception\BusinessException(\app\support\ErrorCode::FORBIDDEN, '无按钮操作权限');
        }
    }

    public function getAdminPermissions(int $adminId): array
    {
        $cacheKey = "admin:permissions:{$adminId}";
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        if ($this->isSuperAdmin($adminId)) {
            $slugs = PermissionModel::pluck('slug')->unique()->values()->toArray();
        } else {
            $slugs = PermissionModel::whereHas('roles.admins', function ($q) use ($adminId) {
                $q->where('sy_admins.id', $adminId);
            })->pluck('slug')->unique()->values()->toArray();
        }

        Redis::setex($cacheKey, 3600, json_encode($slugs));
        return $slugs;
    }

    public function getAdminMenus(int $adminId, array $roles): array
    {
        $cacheKey = "admin:menus:{$adminId}";
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        if (in_array('super_admin', $roles, true)) {
            $menus = PermissionModel::where('type', 'menu')
                ->where('hidden', 0)
                ->orderBy('sort')
                ->get();
        } else {
            $menus = PermissionModel::where('type', 'menu')
                ->where('hidden', 0)
                ->whereHas('roles.admins', function ($q) use ($adminId) {
                    $q->where('sy_admins.id', $adminId);
                })
                ->orderBy('sort')
                ->get();
        }

        $tree = $this->buildTree($menus->toArray());
        Redis::setex($cacheKey, 3600, json_encode($tree));

        return $tree;
    }

    public function getTree(?string $type = null): array
    {
        $query = PermissionModel::query()->orderBy('sort')->orderBy('id');
        if ($type) {
            $query->where('type', $type);
        }
        return $this->buildTree($query->get()->toArray());
    }

    public function clearCache(int $adminId): void
    {
        Redis::del("admin:permissions:{$adminId}", "admin:menus:{$adminId}");
    }

    public function clearCacheForRole(int $roleId): void
    {
        $adminIds = AdminModel::whereHas('roles', static function ($query) use ($roleId) {
            $query->where('sy_roles.id', $roleId);
        })->pluck('id');

        foreach ($adminIds as $adminId) {
            $this->clearCache((int) $adminId);
        }
    }

    public function clearCacheForAll(): void
    {
        foreach (AdminModel::query()->pluck('id') as $adminId) {
            $this->clearCache((int) $adminId);
        }
    }

    public function getAdminDataScope(int $adminId): string
    {
        if ($this->isSuperAdmin($adminId)) {
            return 'all';
        }

        $scopes = RoleModel::whereHas('admins', static function ($query) use ($adminId) {
            $query->where('sy_admins.id', $adminId);
        })->pluck('data_scope')->filter()->unique()->values()->all();

        if (in_array('all', $scopes, true)) {
            return 'all';
        }

        return 'self';
    }

    public function applyDataScope(Builder $query, int $adminId, string $ownerColumn = 'author_id'): Builder
    {
        $scope = $this->getAdminDataScope($adminId);
        if ($scope === 'all') {
            return $query;
        }

        return $query->where($ownerColumn, $adminId);
    }

    public function canAccessOwnedRecord(int $adminId, int $ownerId): bool
    {
        $scope = $this->getAdminDataScope($adminId);
        if ($scope === 'all') {
            return true;
        }

        return $ownerId === $adminId;
    }

    private function isSuperAdmin(int $adminId): bool
    {
        return AdminModel::query()
            ->where('id', $adminId)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))
            ->exists();
    }

    private function getAdminApiPermissions(int $adminId): array
    {
        return PermissionModel::where('type', 'api')
            ->whereHas('roles.admins', function ($q) use ($adminId) {
                $q->where('sy_admins.id', $adminId);
            })
            ->get(['path', 'method'])
            ->toArray();
    }

    private function matchPath(string $pattern, string $path): bool
    {
        $regex = '#^' . str_replace('\*', '[^/]+', preg_quote($pattern, '#')) . '$#';
        return (bool) preg_match($regex, $path);
    }

    private function buildTree(array $items, int $parentId = 0): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ((int) $item['parent_id'] === $parentId) {
                $children = $this->buildTree($items, (int) $item['id']);
                $node = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'slug' => $item['slug'],
                    'type' => $item['type'],
                    'path' => $item['path'] ?? '',
                    'method' => $item['method'] ?? '',
                    'icon' => $item['icon'] ?? '',
                    'component' => $item['component'] ?? '',
                    'sort' => $item['sort'] ?? 0,
                    'parent_id' => (int) ($item['parent_id'] ?? 0),
                    'hidden' => (int) ($item['hidden'] ?? 0),
                ];
                if ($children !== []) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }

        usort($tree, static fn (array $a, array $b): int => ($a['sort'] <=> $b['sort']) ?: ($a['id'] <=> $b['id']));

        return $tree;
    }
}
