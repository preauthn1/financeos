<?php

declare(strict_types=1);

namespace app\service;

use app\model\activity\ActivityRecordModel;

class ActivityRecordService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = ActivityRecordModel::query()->with(['user:id,username,nickname', 'activity:id,title']);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['activity_id'])) {
            $query->where('activity_id', (int) $filters['activity_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (isset($filters['is_virtual']) && $filters['is_virtual'] !== '') {
            $query->where('is_virtual', (int) $filters['is_virtual']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', strtotime($filters['start_date']));
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', strtotime($filters['end_date'] . ' 23:59:59'));
        }

        $statsQuery = clone $query;
        $stats = [
            'total_reward' => (string) (clone $statsQuery)->sum('reward'),
            'claimed_count' => (clone $statsQuery)->where('status', 1)->count(),
        ];

        $total = (clone $query)->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(function ($row) {
                $data = $row->toArray();
                $data['status_text'] = ActivityRecordModel::$statusMap[$row->status] ?? '未知';
                $data['activity_title'] = $row->activity?->title ?? '';
                $data['username'] = $row->user?->username ?? '';
                unset($data['user'], $data['activity']);
                return $data;
            });

        return ['total' => $total, 'items' => $items, 'stats' => $stats];
    }
}
