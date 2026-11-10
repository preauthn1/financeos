<?php

declare(strict_types=1);

namespace app\admin\controller;

use support\Request;

abstract class BaseController
{
    protected function pageParams(Request $request): array
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        return [$page, $limit];
    }

    protected function adminId(Request $request): int
    {
        return (int) ($request->admin_id ?? 0);
    }
}
