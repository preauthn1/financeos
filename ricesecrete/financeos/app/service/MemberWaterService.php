<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\support\DateTimeFormat;
use app\support\ErrorCode;
use app\support\UserWaterDetailType;
use app\support\UserWaterType;

class MemberWaterService
{
    /**
     * 资金流水列表（含统计汇总）
     */
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = $this->buildListQuery($filters);

        // 统计汇总（不分页）
        $statsBase = (clone $query)->getQuery();
        $income = (string) (clone $statsBase)->where('direction', 1)->sum('amount');
        $expense = (string) (clone $statsBase)->where('direction', 2)->sum('amount');
        $stats = [
            ['text' => '收入合计', 'total' => $income],
            ['text' => '支出合计', 'total' => $expense],
        ];

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn (UserWaterModel $row) => $this->formatRow($row))
            ->values()
            ->all();

        return ['total' => $total, 'items' => $items, 'stat' => $stats];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters = [], int $limit = 10000): array
    {
        return $this->buildListQuery($filters)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (UserWaterModel $row) => $this->formatRow($row))
            ->values()
            ->all();
    }

    private function buildListQuery(array $filters)
    {
        $query = UserWaterModel::query()
            ->with(['user:id,username,nickname,invite_code', 'admin:id,username,nickname']);

        // 参考页 username 支持 “用户账户/用户ID”
        if (!empty($filters['username'])) {
            $kw = trim((string) $filters['username']);
            if (ctype_digit($kw)) {
                $query->where('user_id', (int) $kw);
            } else {
                $userIds = UserModel::query()
                    ->where('username', 'like', "%{$kw}%")
                    ->orWhere('nickname', 'like', "%{$kw}%")
                    ->pluck('id')
                    ->map(static fn ($v) => (int) $v)
                    ->all();
                if ($userIds === []) {
                    $query->whereRaw('1=0');
                } else {
                    $query->whereIn('user_id', $userIds);
                }
            }
        } elseif (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', (int) $filters['type']);
        }

        if (array_key_exists('detail_type', $filters) && $filters['detail_type'] !== '' && $filters['detail_type'] !== null) {
            $query->where('detail_type', (int) $filters['detail_type']);
        }

        // 兼容参考 change 字段
        $direction = $filters['direction'] ?? $filters['change'] ?? null;
        if ($direction !== null && $direction !== '') {
            $query->where('direction', (int) $direction);
        }

        if (isset($filters['is_virtual']) && $filters['is_virtual'] !== '') {
            $query->where('is_virtual', (int) $filters['is_virtual']);
        }

        // 兼容参考 startTime/endTime（created_at 为 timestamp 类型）
        $startDate = $filters['start_date'] ?? $filters['startTime'] ?? null;
        $endDate = $filters['end_date'] ?? $filters['endTime'] ?? null;
        if (!empty($startDate)) {
            $start = (string) $startDate;
            $query->where('created_at', '>=', str_contains($start, ':') ? $start : $start . ' 00:00:00');
        }
        if (!empty($endDate)) {
            $end = (string) $endDate;
            $query->where('created_at', '<=', str_contains($end, ':') ? $end : $end . ' 23:59:59');
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function formatRow(UserWaterModel $row): array
    {
        $user = $row->user;
        $admin = $row->admin;
        $direction = (int) ($row->direction ?? 0);
        $type = (int) ($row->type ?? 0);
        $detailType = $row->detail_type === null ? null : (int) $row->detail_type;

        return [
            'id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'order_no' => (int) ($row->order_no ?? 0),
            'user_info' => $user ? sprintf('%s(%s)', (string) $user->username, (string) ($user->nickname ?? '')) : '-',
            'title' => (string) ($row->title ?? ''),
            'type' => $type,
            'type_str' => UserWaterType::label($type),
            'detail_type' => $detailType,
            'detail_type_str' => UserWaterDetailType::label($detailType),
            'user_money' => (string) ($row->before_balance ?? '0'),
            'money' => (string) ($row->amount ?? '0'),
            'last_money' => (string) ($row->after_balance ?? '0'),
            'change_str' => $direction === 1 ? '收入' : '支出',
            'change' => $direction,
            'remarks' => (string) ($row->remark ?? ''),
            'admin_name' => $admin ? ((string) ($admin->nickname ?: $admin->username)) : '-',
            'from_user_info' => '-',
            'create_time' => DateTimeFormat::datetime($row->created_at),
            'created_at' => DateTimeFormat::datetime($row->created_at),
            'status' => (int) ($row->status ?? 1),
            'is_virtual' => (int) ($row->is_virtual ?? 0),
        ];
    }

    public function meta(): array
    {
        $detailOptions = UserWaterDetailType::options();

        return [
            'money_type' => UserWaterType::options(),
            'detail_type' => $detailOptions,
        ];
    }

    public function toggleDisplay(int $id): array
    {
        $row = UserWaterModel::find($id);
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '流水不存在');
        }
        $row->status = (int) ($row->status ?? 1) === 1 ? 0 : 1;
        $row->save();

        return ['id' => (int) $row->id, 'status' => (int) $row->status];
    }

    /**
     * 充值/提现汇总流水视图
     */
    public function rechargeWithdrawFlow(int $page, int $limit, array $filters = []): array
    {
        // 充值类型 detail_type 假设充值=xx, 提现=yy
        $query = UserWaterModel::query()->with('user:id,username,nickname');

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

        if (isset($filters['is_virtual']) && $filters['is_virtual'] !== '') {
            $query->where('is_virtual', (int) $filters['is_virtual']);
        }

        // 汇总
        $statsQuery = clone $query;
        $stats = [
            'total_recharge' => (string) $statsQuery->where('direction', 1)->sum('amount'),
            'total_withdraw' => (string) $statsQuery->where('direction', 2)->sum('amount'),
        ];

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['total' => $total, 'items' => $items, 'stats' => $stats];
    }
}
