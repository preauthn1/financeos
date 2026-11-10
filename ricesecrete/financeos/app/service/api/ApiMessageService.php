<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\member\UserMessageModel;
use app\support\ErrorCode;

class ApiMessageService
{
    public function list(int $userId, int $page, int $limit): array
    {
        $query = UserMessageModel::where('user_id', $userId);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get();
        return ['total' => $total, 'items' => $items];
    }

    public function detail(int $userId, int $id): array
    {
        $msg = UserMessageModel::where('id', $id)->where('user_id', $userId)->first();
        if (!$msg) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '消息不存在');
        }
        if ((int) $msg->is_read === 0) {
            $msg->update(['is_read' => 1, 'read_at' => time()]);
        }
        return $msg->toArray();
    }

    public function popList(int $userId): array
    {
        $items = UserMessageModel::where('user_id', $userId)
            ->where('is_popup', 1)->where('is_read', 0)
            ->orderByDesc('id')->limit(20)->get();
        if ($items->isNotEmpty()) {
            UserMessageModel::whereIn('id', $items->pluck('id'))->update(['is_read' => 1, 'read_at' => time()]);
        }
        return $items->toArray();
    }
}
