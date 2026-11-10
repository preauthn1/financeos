<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserBrokeragePoolModel;
use app\model\member\UserModel;
use app\support\DateTimeFormat;
use app\support\ErrorCode;

class MemberBrokeragePoolService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = UserBrokeragePoolModel::query()->with('user:id,username,nickname');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['start_date'])) {
            $start = (string) $filters['start_date'];
            $query->where('created_at', '>=', str_contains($start, ':') ? $start : $start . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $end = (string) $filters['end_date'];
            $query->where('created_at', '<=', str_contains($end, ':') ? $end : $end . ' 23:59:59');
        }

        // 汇总
        $statsQuery = clone $query;
        $stats = [
            'total_receivable' => (string) $statsQuery->sum('receivable'),
            'total_received'   => (string) $statsQuery->sum('received'),
            'pending'          => (string) ($statsQuery->sum('receivable') - $statsQuery->sum('received')),
        ];

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn ($pool) => $this->formatItem($pool));

        return ['total' => $total, 'items' => $items, 'stats' => $stats];
    }

    private function formatItem(UserBrokeragePoolModel $pool): array
    {
        $pending = max(0, (float) $pool->receivable - (float) $pool->received);
        $directCount = UserModel::where('leader1', $pool->user_id)->count();

        return [
            'id'                   => $pool->id,
            'user_id'              => $pool->user_id,
            'username'             => $pool->user->username ?? '',
            'nickname'             => $pool->user->nickname ?? '',
            'receivable'           => $pool->receivable,
            'received'             => $pool->received,
            'pending'              => $pending,
            'total_commission'     => $pool->receivable,
            'pending_commission'   => $pending,
            'released_commission'  => $pool->received,
            'withdrawn_commission' => $pool->received,
            'direct_count'         => $directCount,
            'team_count'           => $directCount,
            'status'               => $pending > 0 ? 1 : 0,
            'created_at'           => DateTimeFormat::datetime($pool->created_at),
            'updated_at'           => DateTimeFormat::datetime($pool->updated_at),
        ];
    }
}
