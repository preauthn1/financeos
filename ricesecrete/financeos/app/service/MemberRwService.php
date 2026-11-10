<?php

declare(strict_types=1);

namespace app\service;

use app\model\pay\RechargeOrderModel;
use app\model\pay\WithdrawOrderModel;
use app\support\DateTimeFormat;
use Illuminate\Database\Capsule\Manager as DB;

class MemberRwService
{
    /**
     * 会员充值/提现统一流水（合并 pa_recharge_order + pa_withdraw_order）
     */
    public function flow(int $page, int $limit, array $filters = []): array
    {
        [$items, $total, $stats] = $this->queryFlow($filters, $page, $limit);

        return ['total' => $total, 'items' => $items, 'stats' => $stats];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters = [], int $limit = 10000): array
    {
        [$items] = $this->queryFlow($filters, 1, $limit);

        return $items;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int, 2: array<string, string|int>}
     */
    private function queryFlow(array $filters, int $page, int $limit): array
    {
        $rechargeBase = RechargeOrderModel::query()
            ->where('status', RechargeOrderModel::STATUS_SUCCESS);
        $withdrawBase = WithdrawOrderModel::query()
            ->where('status', WithdrawOrderModel::STATUS_SUCCESS);

        $this->applyRwFilters($rechargeBase, $withdrawBase, $filters);

        $stats = [
            'total_recharge' => (string) (clone $rechargeBase)->sum('money'),
            'total_withdraw' => (string) (clone $withdrawBase)->sum('original_amount'),
            'virtual_count' => (int) (clone $rechargeBase)->where('is_virtual', 1)->count()
                + (int) (clone $withdrawBase)->where('is_virtual', 1)->count(),
        ];
        $stats['rw_diff'] = bcsub($stats['total_recharge'], $stats['total_withdraw'], 2);

        $rechargeQuery = (clone $rechargeBase)->select([
            'id', 'user_id', 'order_no', 'money as amount',
            'status', 'is_virtual', 'created_at', 'paid_at',
            DB::raw("'recharge' as flow_type"),
        ]);
        $withdrawQuery = (clone $withdrawBase)->select([
            'id', 'user_id', 'order_no', 'original_amount as amount',
            'status', 'is_virtual', 'created_at', 'paid_at',
            DB::raw("'withdraw' as flow_type"),
        ]);

        if (!empty($filters['flow_type']) && $filters['flow_type'] === 'recharge') {
            $union = $rechargeQuery;
            $stats['virtual_count'] = (int) (clone $rechargeBase)->where('is_virtual', 1)->count();
        } elseif (!empty($filters['flow_type']) && $filters['flow_type'] === 'withdraw') {
            $union = $withdrawQuery;
            $stats['virtual_count'] = (int) (clone $withdrawBase)->where('is_virtual', 1)->count();
        } else {
            $union = $rechargeQuery->unionAll($withdrawQuery);
        }

        $baseQuery = DB::query()->fromSub($union, 'rw_flow');
        $total = (clone $baseQuery)->count();
        $stats['record_count'] = $total;

        $rows = (clone $baseQuery)
            ->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $userIds = $rows->pluck('user_id')->unique()->filter()->all();
        $users = \app\model\member\UserModel::whereIn('id', $userIds)
            ->get(['id', 'username', 'nickname'])
            ->keyBy('id');

        $items = $rows->map(function ($row) use ($users) {
            $user = $users[$row->user_id] ?? null;
            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'username' => $user?->username ?? '',
                'nickname' => $user?->nickname ?? '',
                'order_no' => $row->order_no,
                'amount' => (string) $row->amount,
                'flow_type' => $row->flow_type,
                'flow_type_text' => $row->flow_type === 'recharge' ? '充值' : '提现',
                'status' => $row->status,
                'is_virtual' => (int) $row->is_virtual,
                'created_at' => DateTimeFormat::datetime($row->created_at),
                'paid_at' => DateTimeFormat::datetime($row->paid_at),
            ];
        })->all();

        return [$items, $total, $stats];
    }

    private function applyRwFilters($rechargeQuery, $withdrawQuery, array $filters): void
    {
        if (!empty($filters['user_id'])) {
            $uid = (int) $filters['user_id'];
            $rechargeQuery->where('user_id', $uid);
            $withdrawQuery->where('user_id', $uid);
        }
        if (isset($filters['is_virtual']) && $filters['is_virtual'] !== '') {
            $virtual = (int) $filters['is_virtual'];
            $rechargeQuery->where('is_virtual', $virtual);
            $withdrawQuery->where('is_virtual', $virtual);
        }
        if (!empty($filters['start_date'])) {
            $start = $filters['start_date'] . ' 00:00:00';
            $rechargeQuery->where('created_at', '>=', $start);
            $withdrawQuery->where('created_at', '>=', $start);
        }
        if (!empty($filters['end_date'])) {
            $end = $filters['end_date'] . ' 23:59:59';
            $rechargeQuery->where('created_at', '<=', $end);
            $withdrawQuery->where('created_at', '<=', $end);
        }
    }
}
