<?php

declare(strict_types=1);

namespace app\admin\controller\concerns;

use app\exception\BusinessException;
use app\service\PermissionService;
use app\support\ErrorCode;
use support\Request;

trait RequiresPermission
{
    protected function requireButtonPermission(Request $request, string $slug): void
    {
        $adminId = (int) ($request->admin_id ?? 0);
        if ($adminId <= 0) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED);
        }

        $permissionService = new PermissionService();
        $permissionService->assertButtonPermission($adminId, $slug);
    }
}
