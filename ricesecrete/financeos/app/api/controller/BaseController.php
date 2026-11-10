<?php

declare(strict_types=1);

namespace app\api\controller;

use app\model\member\UserModel;
use support\Request;

abstract class BaseController
{
    protected function userId(Request $request): int
    {
        return (int) ($request->user_id ?? 0);
    }

    protected function pageParams(Request $request): array
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        return [$page, $limit];
    }

    protected function user(Request $request): UserModel
    {
        $userId = $this->userId($request);
        $user = UserModel::find($userId);
        if (!$user) {
            throw new \app\exception\BusinessException(\app\support\ErrorCode::NOT_FOUND, '用户不存在');
        }
        return $user;
    }
}
