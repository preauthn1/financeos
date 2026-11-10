<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserVirtualLogModel;
use app\model\member\UserVirtualModel;
use app\support\DateTimeFormat;
use app\support\ErrorCode;

class MemberVirtualService
{
    /**
     * 虚拟数据列表
     */
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = UserVirtualModel::query()->with('user:id,username,nickname,is_virtual');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn (UserVirtualModel $item) => $this->formatVirtualItem($item));

        return ['total' => $total, 'items' => $items];
    }

    /**
     * 更新虚拟数据
     */
    public function update(int $id, array $data): array
    {
        $virtual = UserVirtualModel::find($id);
        if (!$virtual) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '虚拟数据不存在');
        }

        // 只允许更新可编辑字段
        $allowed = [
            'balance', 'total_income', 'today_income',
            'total_recharge', 'total_withdraw',
            'team_total', 'team_today_new', 'team_active', 'team_total_recharge',
            'level1_count', 'level1_active', 'level1_recharge', 'level1_brokerage',
            'level2_count', 'level2_active', 'level2_recharge', 'level2_brokerage',
            'level3_count', 'level3_active', 'level3_recharge', 'level3_brokerage',
            'today_invite_count', 'week_invite_count', 'month_invite_count',
            'credit_score',
        ];

        $filtered = array_intersect_key($data, array_flip($allowed));
        $virtual->update($filtered);

        return $this->formatVirtualItem($virtual->fresh('user'));
    }

    /**
     * 虚拟数据变更日志
     */
    public function logList(int $page, int $limit, array $filters = []): array
    {
        $query = UserVirtualLogModel::query()->with('user:id,username,nickname');

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

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn (UserVirtualLogModel $log) => $this->formatLogItem($log));

        return ['total' => $total, 'items' => $items];
    }

    /**
     * 手动调整虚拟数据（产生日志）
     */
    public function adjust(int $userId, string $field, string $amount, string $remark, int $adminId): array
    {
        $virtual = UserVirtualModel::where('user_id', $userId)->first();
        if (!$virtual) {
            $virtual = UserVirtualModel::create(['user_id' => $userId]);
        }

        $before = (float) ($virtual->{$field} ?? 0);
        $after  = $before + (float) $amount;

        $virtual->{$field} = $after;
        $virtual->save();

        // 记录日志
        UserVirtualLogModel::create([
            'user_id'        => $userId,
            'admin_id'       => $adminId,
            'type'           => 1,
            'direction'      => (float) $amount >= 0 ? 1 : 2,
            'title'          => $remark ?: "手动调整虚拟数据",
            'amount'         => $amount,
            'before_balance' => $before,
            'after_balance'  => $after,
            'remark'         => $remark,
        ]);

        return ['before' => $before, 'after' => $after, 'changed' => $amount];
    }

    public function deleteVirtualLog(int $id): void
    {
        $log = UserVirtualLogModel::find($id);
        if ($log) {
            $log->delete();
        }
    }

    private function formatVirtualItem(UserVirtualModel $virtual): array
    {
        return [
            'id'                  => $virtual->id,
            'user_id'             => $virtual->user_id,
            'username'            => $virtual->user->username ?? '',
            'nickname'            => $virtual->user->nickname ?? '',
            'is_virtual'          => (int) ($virtual->user->is_virtual ?? 0),
            'balance'             => $virtual->balance,
            'total_income'        => $virtual->total_income,
            'today_income'        => $virtual->today_income,
            'total_recharge'      => $virtual->total_recharge,
            'total_withdraw'      => $virtual->total_withdraw,
            'team_total'          => $virtual->team_total,
            'team_today_new'      => $virtual->team_today_new,
            'team_active'         => $virtual->team_active,
            'team_total_recharge' => $virtual->team_total_recharge,
            'level1_count'        => $virtual->level1_count,
            'level1_active'       => $virtual->level1_active,
            'level1_recharge'     => $virtual->level1_recharge,
            'level1_brokerage'    => $virtual->level1_brokerage,
            'level2_count'        => $virtual->level2_count,
            'level2_active'       => $virtual->level2_active,
            'level2_recharge'     => $virtual->level2_recharge,
            'level2_brokerage'    => $virtual->level2_brokerage,
            'level3_count'        => $virtual->level3_count,
            'level3_active'       => $virtual->level3_active,
            'level3_recharge'     => $virtual->level3_recharge,
            'level3_brokerage'    => $virtual->level3_brokerage,
            'today_invite_count'  => $virtual->today_invite_count,
            'week_invite_count'   => $virtual->week_invite_count,
            'month_invite_count'  => $virtual->month_invite_count,
            'credit_score'        => $virtual->credit_score,
            'created_at'          => DateTimeFormat::datetime($virtual->created_at),
            'updated_at'          => DateTimeFormat::datetime($virtual->updated_at),
        ];
    }

    private function formatLogItem(UserVirtualLogModel $log): array
    {
        return [
            'id'             => $log->id,
            'user_id'        => $log->user_id,
            'username'       => $log->user->username ?? '',
            'nickname'       => $log->user->nickname ?? '',
            'admin_id'       => $log->admin_id,
            'type'           => $log->type,
            'direction'      => $log->direction,
            'title'          => $log->title,
            'amount'         => $log->amount,
            'before_balance' => $log->before_balance,
            'after_balance'  => $log->after_balance,
            'remark'         => $log->remark,
            'created_at'     => DateTimeFormat::datetime($log->created_at),
            'updated_at'     => DateTimeFormat::datetime($log->updated_at),
        ];
    }
}
