<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\sys\AdminNotificationModel;
use app\support\ErrorCode;

class NotificationService
{
    public function listForAdmin(int $adminId, int $page, int $limit, array $filters = []): array
    {
        $query = AdminNotificationModel::query()
            ->where(function ($q) use ($adminId) {
                $q->where('admin_id', $adminId)->orWhere('admin_id', 0);
            });

        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $query->where('is_read', (bool) $filters['is_read']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items];
    }

    public function unreadCount(int $adminId): int
    {
        return AdminNotificationModel::query()
            ->where(function ($q) use ($adminId) {
                $q->where('admin_id', $adminId)->orWhere('admin_id', 0);
            })
            ->where('is_read', false)
            ->count();
    }

    public function create(array $data): AdminNotificationModel
    {
        return AdminNotificationModel::create($data);
    }

    public function markRead(int $id, int $adminId): void
    {
        $notification = AdminNotificationModel::find($id);
        if (!$notification) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '通知不存在');
        }
        if ($notification->admin_id !== 0 && $notification->admin_id !== $adminId) {
            throw new BusinessException(ErrorCode::FORBIDDEN, '无权操作该通知');
        }

        $notification->update([
            'is_read' => true,
            'read_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markAllRead(int $adminId): void
    {
        AdminNotificationModel::query()
            ->where(function ($q) use ($adminId) {
                $q->where('admin_id', $adminId)->orWhere('admin_id', 0);
            })
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
