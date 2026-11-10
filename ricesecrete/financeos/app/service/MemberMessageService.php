<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserMessageModel;
use app\model\member\UserModel;
use app\support\ErrorCode;

class MemberMessageService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = UserMessageModel::query()->with('user:id,username,nickname');

        if (!empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where('title', 'like', "%{$kw}%");
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', (int) $filters['type']);
        }

        if (isset($filters['is_popup']) && $filters['is_popup'] !== '') {
            $query->where('is_popup', (int) $filters['is_popup']);
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items];
    }

    /**
     * 发送站内信给指定用户
     */
    public function sendToUser(int $userId, array $data): UserMessageModel
    {
        $user = UserModel::find($userId);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        return UserMessageModel::create([
            'user_id'    => $userId,
            'admin_id'   => $data['admin_id'] ?? 0,
            'type'       => (int) ($data['type'] ?? 1),
            'title'      => (string) ($data['title'] ?? ''),
            'content'    => (string) ($data['content'] ?? ''),
            'is_popup'     => (int) ($data['is_popup'] ?? 0),
        ]);
    }

    /**
     * 批量发送站内信（按用户ID列表）
     */
    public function sendToUsers(array $userIds, array $data): int
    {
        $now = time();
        $count = 0;

        foreach ($userIds as $uid) {
            UserMessageModel::create([
                'user_id'    => (int) $uid,
                'admin_id'   => $data['admin_id'] ?? 0,
                'type'       => (int) ($data['type'] ?? 1),
                'title'      => (string) ($data['title'] ?? ''),
                'content'    => (string) ($data['content'] ?? ''),
                'is_popup'     => (int) ($data['is_popup'] ?? 0),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * 按条件群发站内信
     */
    public function broadcast(array $data): int
    {
        $query = UserModel::query()->where('status', 1);

        if (!empty($data['level_id'])) {
            $query->where('level_id', (int) $data['level_id']);
        }
        if (!empty($data['leader_id'])) {
            $query->where('leader1', (int) $data['leader_id']);
        }
        if (isset($data['is_virtual'])) {
            $query->where('is_virtual', (int) $data['is_virtual']);
        }

        $userIds = $query->pluck('id')->toArray();
        if ($userIds === []) {
            return 0;
        }

        return $this->sendToUsers($userIds, $data);
    }

    public function delete(int $id): void
    {
        $msg = UserMessageModel::find($id);
        if (!$msg) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '消息不存在');
        }
        $msg->delete();
    }
}
