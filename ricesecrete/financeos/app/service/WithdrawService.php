<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\model\pay\WithdrawOrderModel;
use app\model\sys\AdminModel;
use app\support\ErrorCode;
use app\support\IpLocationResolver;
use Illuminate\Database\Capsule\Manager as DB;

class WithdrawService
{
    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = WithdrawOrderModel::query()->with([
            'user:id,username,nickname,real_name,mobile,is_virtual,balance,leader1,level_id,total_recharge,total_withdraw,last_login_at',
        ]);

        $this->applyFilters($query, $filters);
        $kpis = $this->buildKpis();

        $tab = (string) ($filters['tab'] ?? '');
        if ($tab === 'risk') {
            $rows = $query->orderBy('id', 'desc')->limit(500)->get();
            $adminIds = $rows->flatMap(static fn (WithdrawOrderModel $row) => [
                (int) $row->admin_id,
                (int) ($row->audit_admin_id ?? 0),
                (int) ($row->payout_admin_id ?? 0),
                (int) ($row->locked_by ?? 0),
            ])->filter()->unique()->values()->all();
            $admins = $this->adminNameMap($adminIds);
            $leaderIds = $rows->map(static fn (WithdrawOrderModel $row) => (int) ($row->user?->leader1 ?? 0))
                ->filter()->unique()->values()->all();
            $leaders = $this->userLabelMap($leaderIds);
            $userIds = $rows->pluck('user_id')->unique()->filter()->all();
            $withdrawCounts = $this->withdrawCountMap($userIds);
            $todayCounts = $this->todayWithdrawCountMap($userIds);

            $items = $rows->map(function (WithdrawOrderModel $row) use ($admins, $leaders, $withdrawCounts, $todayCounts) {
                return $this->formatItem($row, false, $admins, $leaders, $withdrawCounts, $todayCounts);
            })->filter(static fn (array $item) => (int) $item['risk_score'] >= 60)->values();

            $total = $items->count();
            $paged = $items->slice(($page - 1) * $limit, $limit)->values()->all();

            return ['total' => $total, 'items' => $paged, 'kpis' => $kpis];
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $adminIds = $rows->flatMap(static fn (WithdrawOrderModel $row) => [
            (int) $row->admin_id,
            (int) ($row->audit_admin_id ?? 0),
            (int) ($row->payout_admin_id ?? 0),
            (int) ($row->locked_by ?? 0),
        ])->filter()->unique()->values()->all();

        $admins = $this->adminNameMap($adminIds);
        $leaderIds = $rows->map(static fn (WithdrawOrderModel $row) => (int) ($row->user?->leader1 ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $leaders = $this->userLabelMap($leaderIds);

        $userIds = $rows->pluck('user_id')->unique()->filter()->all();
        $withdrawCounts = $this->withdrawCountMap($userIds);
        $todayCounts = $this->todayWithdrawCountMap($userIds);

        $items = $rows->map(function (WithdrawOrderModel $row) use ($admins, $leaders, $withdrawCounts, $todayCounts) {
            return $this->formatItem($row, false, $admins, $leaders, $withdrawCounts, $todayCounts);
        })->all();

        return ['total' => $total, 'items' => $items, 'kpis' => $kpis];
    }

    public function show(int $id): array
    {
        $order = WithdrawOrderModel::with([
            'user:id,username,nickname,real_name,mobile,is_virtual,balance,leader1,level_id,total_recharge,total_withdraw,last_login_at',
        ])->find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '提现订单不存在');
        }

        $adminIds = array_filter([
            (int) $order->admin_id,
            (int) ($order->audit_admin_id ?? 0),
            (int) ($order->payout_admin_id ?? 0),
            (int) ($order->locked_by ?? 0),
        ]);
        $admins = $this->adminNameMap($adminIds);
        $leaderId = (int) ($order->user?->leader1 ?? 0);
        $leaders = $leaderId > 0 ? $this->userLabelMap([$leaderId]) : [];
        $uid = (int) $order->user_id;

        return $this->formatItem(
            $order,
            true,
            $admins,
            $leaders,
            $this->withdrawCountMap([$uid]),
            $this->todayWithdrawCountMap([$uid])
        );
    }

    public function audit(int $id, int $status, string $remark, int $adminId = 0): WithdrawOrderModel
    {
        $allowed = [
            WithdrawOrderModel::STATUS_AGREE,
            WithdrawOrderModel::STATUS_REJECT,
            WithdrawOrderModel::STATUS_SUCCESS,
            WithdrawOrderModel::STATUS_FAIL,
        ];
        if (!in_array($status, $allowed, true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '审核状态有误');
        }

        return DB::transaction(function () use ($id, $status, $remark, $adminId) {
            $order = WithdrawOrderModel::where('id', $id)->lockForUpdate()->first();
            if (!$order) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '提现订单不存在');
            }

            $current = (int) $order->status;
            if ($status === WithdrawOrderModel::STATUS_AGREE
                && !in_array($current, [
                    WithdrawOrderModel::STATUS_PENDING,
                    WithdrawOrderModel::STATUS_WAIT,
                    WithdrawOrderModel::STATUS_CARD_REVIEW,
                ], true)
            ) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '当前状态不可审核通过');
            }
            if ($status === WithdrawOrderModel::STATUS_REJECT
                && !in_array($current, [
                    WithdrawOrderModel::STATUS_PENDING,
                    WithdrawOrderModel::STATUS_WAIT,
                    WithdrawOrderModel::STATUS_AGREE,
                    WithdrawOrderModel::STATUS_CARD_REVIEW,
                    WithdrawOrderModel::STATUS_PAYING,
                ], true)
            ) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '当前状态不可驳回');
            }
            if ($status === WithdrawOrderModel::STATUS_SUCCESS
                && !in_array($current, [WithdrawOrderModel::STATUS_AGREE, WithdrawOrderModel::STATUS_PAYING], true)
            ) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '仅待出款/出款中订单可确认出款');
            }
            if ($status === WithdrawOrderModel::STATUS_FAIL
                && !in_array($current, [WithdrawOrderModel::STATUS_AGREE, WithdrawOrderModel::STATUS_PAYING], true)
            ) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '仅待出款/出款中订单可标记失败');
            }

            $alreadyFrozen = $this->wasFrozenOnApply($order);

            if ($status === WithdrawOrderModel::STATUS_AGREE && !$alreadyFrozen) {
                $this->freezeUserBalance($order);
            }

            if (in_array($status, [WithdrawOrderModel::STATUS_REJECT, WithdrawOrderModel::STATUS_FAIL], true)) {
                if ($alreadyFrozen || in_array($current, [
                    WithdrawOrderModel::STATUS_AGREE,
                    WithdrawOrderModel::STATUS_PAYING,
                ], true)) {
                    $this->refundUserBalance($order);
                }
            }

            if ($status === WithdrawOrderModel::STATUS_SUCCESS) {
                $this->confirmPayout($order);
                $order->paid_at = time();
                $order->payout_admin_id = $adminId;
            }

            if (in_array($status, [WithdrawOrderModel::STATUS_AGREE, WithdrawOrderModel::STATUS_REJECT], true)) {
                $order->audit_admin_id = $adminId;
                $order->checked_at = time();
            }

            $order->status = $status;
            $order->remark = $remark !== '' ? $remark : $order->remark;
            $order->admin_id = $adminId;
            if ($status === WithdrawOrderModel::STATUS_SUCCESS || $status === WithdrawOrderModel::STATUS_FAIL) {
                $order->checked_at = time();
            }
            $order->save();

            return $order->fresh('user');
        });
    }

    public function setCardReview(int $id, bool $enable, string $reason, int $adminId = 0): WithdrawOrderModel
    {
        return DB::transaction(function () use ($id, $enable, $reason, $adminId) {
            $order = WithdrawOrderModel::where('id', $id)->lockForUpdate()->first();
            if (!$order) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '提现订单不存在');
            }

            $current = (int) $order->status;
            if ($enable) {
                if (!in_array($current, [
                    WithdrawOrderModel::STATUS_PENDING,
                    WithdrawOrderModel::STATUS_WAIT,
                    WithdrawOrderModel::STATUS_AGREE,
                ], true)) {
                    throw new BusinessException(ErrorCode::VALIDATION_FAILED, '当前状态不可转入卡审核');
                }
                $order->status = WithdrawOrderModel::STATUS_CARD_REVIEW;
                $order->card_reason = $reason !== '' ? $reason : '人工卡审核';
            } else {
                if ($current !== WithdrawOrderModel::STATUS_CARD_REVIEW) {
                    throw new BusinessException(ErrorCode::VALIDATION_FAILED, '订单不在卡审核状态');
                }
                $order->status = WithdrawOrderModel::STATUS_WAIT;
                $order->card_reason = '';
            }

            $order->audit_admin_id = $adminId;
            $order->admin_id = $adminId;
            $order->checked_at = time();
            $order->save();

            return $order->fresh('user');
        });
    }

    public function startPayout(int $id, int $adminId = 0): WithdrawOrderModel
    {
        return DB::transaction(function () use ($id, $adminId) {
            $order = WithdrawOrderModel::where('id', $id)->lockForUpdate()->first();
            if (!$order) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '提现订单不存在');
            }
            if ((int) $order->status !== WithdrawOrderModel::STATUS_AGREE) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '仅待出款订单可进入出款');
            }

            $order->status = WithdrawOrderModel::STATUS_PAYING;
            $order->payout_admin_id = $adminId;
            $order->admin_id = $adminId;
            if ((int) ($order->locked_by ?? 0) === 0) {
                $order->locked_by = $adminId;
                $order->locked_at = time();
            }
            $order->save();

            return $order->fresh('user');
        });
    }

    public function failPayout(int $id, string $remark, int $adminId = 0): WithdrawOrderModel
    {
        return $this->audit($id, WithdrawOrderModel::STATUS_FAIL, $remark !== '' ? $remark : '出款失败', $adminId);
    }

    public function offlineTransfer(int $id, string $remark, int $adminId = 0): WithdrawOrderModel
    {
        return $this->audit(
            $id,
            WithdrawOrderModel::STATUS_SUCCESS,
            $remark !== '' ? $remark : '线下转账完成',
            $adminId
        );
    }

    public function lock(int $id, int $adminId, bool $force = false): WithdrawOrderModel
    {
        return DB::transaction(function () use ($id, $adminId, $force) {
            $order = WithdrawOrderModel::where('id', $id)->lockForUpdate()->first();
            if (!$order) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '提现订单不存在');
            }

            $lockedBy = (int) ($order->locked_by ?? 0);
            if ($lockedBy > 0 && $lockedBy !== $adminId && !$force) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '订单已被其他管理员锁定');
            }

            $order->locked_by = $adminId;
            $order->locked_at = time();
            $order->save();

            return $order->fresh('user');
        });
    }

    public function unlock(int $id, int $adminId, bool $force = false): WithdrawOrderModel
    {
        return DB::transaction(function () use ($id, $adminId, $force) {
            $order = WithdrawOrderModel::where('id', $id)->lockForUpdate()->first();
            if (!$order) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '提现订单不存在');
            }

            $lockedBy = (int) ($order->locked_by ?? 0);
            if ($lockedBy > 0 && $lockedBy !== $adminId && !$force) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '仅锁单人或超管可解锁');
            }

            $order->locked_by = 0;
            $order->locked_at = 0;
            $order->save();

            return $order->fresh('user');
        });
    }

    /**
     * @param list<int> $ids
     */
    public function batchAction(array $ids, string $action, string $remark, int $adminId = 0): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请选择订单');
        }

        $success = 0;
        $failed = 0;
        foreach ($ids as $id) {
            try {
                match ($action) {
                    'agree' => $this->audit($id, WithdrawOrderModel::STATUS_AGREE, $remark, $adminId),
                    'reject' => $this->audit($id, WithdrawOrderModel::STATUS_REJECT, $remark, $adminId),
                    'card' => $this->setCardReview($id, true, $remark, $adminId),
                    'uncard' => $this->setCardReview($id, false, $remark, $adminId),
                    'start_payout' => $this->startPayout($id, $adminId),
                    'success' => $this->offlineTransfer($id, $remark, $adminId),
                    'fail' => $this->failPayout($id, $remark, $adminId),
                    default => throw new BusinessException(ErrorCode::VALIDATION_FAILED, '不支持的批量操作'),
                };
                $success++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * @param list<int> $ids
     */
    public function batchMessage(array $ids, string $title, string $content, int $adminId = 0): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请选择订单');
        }
        if ($title === '' || $content === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '标题和内容不能为空');
        }

        $userIds = WithdrawOrderModel::whereIn('id', $ids)->pluck('user_id')->unique()->map(static fn ($v) => (int) $v)->all();
        $count = (new MemberMessageService())->sendToUsers($userIds, [
            'admin_id' => $adminId,
            'title' => $title,
            'content' => $content,
            'type' => 1,
            'is_popup' => 0,
        ]);

        return ['user_count' => count($userIds), 'message_count' => $count];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters = [], int $limit = 10000): array
    {
        $query = WithdrawOrderModel::query()->with([
            'user:id,username,nickname,real_name,mobile,is_virtual,balance,leader1,level_id,total_recharge,total_withdraw,last_login_at',
        ]);
        $this->applyFilters($query, $filters);

        $rows = $query->orderByDesc('id')->limit($limit)->get();
        $adminIds = $rows->flatMap(static fn (WithdrawOrderModel $row) => [
            (int) $row->admin_id,
            (int) ($row->audit_admin_id ?? 0),
            (int) ($row->payout_admin_id ?? 0),
            (int) ($row->locked_by ?? 0),
        ])->filter()->unique()->values()->all();
        $admins = $this->adminNameMap($adminIds);
        $leaderIds = $rows->map(static fn (WithdrawOrderModel $row) => (int) ($row->user?->leader1 ?? 0))
            ->filter()->unique()->values()->all();
        $leaders = $this->userLabelMap($leaderIds);
        $userIds = $rows->pluck('user_id')->unique()->filter()->all();
        $withdrawCounts = $this->withdrawCountMap($userIds);
        $todayCounts = $this->todayWithdrawCountMap($userIds);

        return $rows->map(function (WithdrawOrderModel $row) use ($admins, $leaders, $withdrawCounts, $todayCounts) {
            return $this->formatItem($row, false, $admins, $leaders, $withdrawCounts, $todayCounts);
        })->all();
    }

    /**
     * 三方代付成功回调（幂等）
     */
    public function notifySuccess(string $orderNo): WithdrawOrderModel
    {
        return DB::transaction(function () use ($orderNo) {
            $order = WithdrawOrderModel::query()
                ->where('order_no', $orderNo)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '提现订单不存在');
            }

            if ((int) $order->status === WithdrawOrderModel::STATUS_SUCCESS) {
                return $order;
            }

            if (!in_array((int) $order->status, [
                WithdrawOrderModel::STATUS_AGREE,
                WithdrawOrderModel::STATUS_PAYING,
            ], true)) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '订单状态不可确认出款');
            }

            $this->confirmPayout($order);
            $order->status = WithdrawOrderModel::STATUS_SUCCESS;
            $order->paid_at = time();
            $order->checked_at = time();
            $order->remark = $order->remark !== '' ? $order->remark : '三方代付成功';
            $order->save();

            return $order->fresh('user');
        });
    }

    public function movePendingToWait(): int
    {
        $hours = (float) $this->settingService->getValue('withdraw_check_hour', 0);
        if ($hours <= 0) {
            return 0;
        }

        $deadline = date('Y-m-d H:i:s', time() - (int) ($hours * 3600));

        return WithdrawOrderModel::query()
            ->where('status', WithdrawOrderModel::STATUS_PENDING)
            ->where('created_at', '<', $deadline)
            ->update(['status' => WithdrawOrderModel::STATUS_WAIT]);
    }

    private function applyFilters($query, array $filters): void
    {
        $tab = (string) ($filters['tab'] ?? '');
        if ($tab !== '') {
            match ($tab) {
                'audit' => $query->whereIn('status', [
                    WithdrawOrderModel::STATUS_PENDING,
                    WithdrawOrderModel::STATUS_WAIT,
                    WithdrawOrderModel::STATUS_CARD_REVIEW,
                ]),
                'payout' => $query->whereIn('status', [
                    WithdrawOrderModel::STATUS_AGREE,
                    WithdrawOrderModel::STATUS_PAYING,
                    WithdrawOrderModel::STATUS_FAIL,
                    WithdrawOrderModel::STATUS_OFFLINE_FAIL,
                ]),
                'card' => $query->where('status', WithdrawOrderModel::STATUS_CARD_REVIEW),
                'paid' => $query->where('status', WithdrawOrderModel::STATUS_SUCCESS),
                'risk' => $query->whereNotIn('status', [
                    WithdrawOrderModel::STATUS_SUCCESS,
                    WithdrawOrderModel::STATUS_REJECT,
                ]),
                default => null,
            };
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['leader_id'])) {
            $leaderId = (int) $filters['leader_id'];
            $query->whereHas('user', static fn ($q) => $q->where('leader1', $leaderId));
        }
        if (!empty($filters['order_no'])) {
            $query->where('order_no', 'like', '%' . $filters['order_no'] . '%');
        }
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('order_no', 'like', "%{$kw}%")
                    ->orWhere('bank_number', 'like', "%{$kw}%")
                    ->orWhere('withdrawal_address', 'like', "%{$kw}%");
                if (ctype_digit($kw)) {
                    $q->orWhere('user_id', (int) $kw)
                        ->orWhereHas('user', static fn ($uq) => $uq->where('leader1', (int) $kw));
                }
                $q->orWhereHas('user', function ($uq) use ($kw) {
                    $uq->where('username', 'like', "%{$kw}%")
                        ->orWhere('mobile', 'like', "%{$kw}%");
                });
            });
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (isset($filters['type']) && $filters['type'] !== '') {
            $query->where('type', (int) $filters['type']);
        }
        if (isset($filters['is_virtual']) && $filters['is_virtual'] !== '') {
            $query->where('is_virtual', (int) $filters['is_virtual']);
        }
        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        $timeField = match ((string) ($filters['time_type'] ?? 'created')) {
            'checked' => 'checked_at',
            'paid' => 'paid_at',
            default => 'created_at',
        };

        $start = (string) ($filters['start_date'] ?? $filters['start_time'] ?? '');
        $end = (string) ($filters['end_date'] ?? $filters['end_time'] ?? '');
        if ($timeField === 'created_at') {
            if ($start !== '') {
                $query->where('created_at', '>=', str_contains($start, ':') ? $start : $start . ' 00:00:00');
            }
            if ($end !== '') {
                $query->where('created_at', '<=', str_contains($end, ':') ? $end : $end . ' 23:59:59');
            }
        } else {
            if ($start !== '') {
                $ts = strtotime(str_contains($start, ':') ? $start : $start . ' 00:00:00') ?: 0;
                $query->where($timeField, '>=', $ts);
            }
            if ($end !== '') {
                $ts = strtotime(str_contains($end, ':') ? $end : $end . ' 23:59:59') ?: 0;
                $query->where($timeField, '<=', $ts);
            }
        }
    }

    private function buildKpis(): array
    {
        $todayStart = strtotime(date('Y-m-d 00:00:00')) ?: 0;
        $pendingStatuses = [WithdrawOrderModel::STATUS_PENDING, WithdrawOrderModel::STATUS_WAIT];

        $pendingQuery = WithdrawOrderModel::query()->whereIn('status', $pendingStatuses);
        $cardQuery = WithdrawOrderModel::query()->where('status', WithdrawOrderModel::STATUS_CARD_REVIEW);
        $agreeQuery = WithdrawOrderModel::query()->where('status', WithdrawOrderModel::STATUS_AGREE);
        $payingQuery = WithdrawOrderModel::query()->where('status', WithdrawOrderModel::STATUS_PAYING);
        $paidTodayQuery = WithdrawOrderModel::query()
            ->where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->where('paid_at', '>=', $todayStart);
        $failQuery = WithdrawOrderModel::query()->whereIn('status', [
            WithdrawOrderModel::STATUS_FAIL,
            WithdrawOrderModel::STATUS_OFFLINE_FAIL,
        ]);

        return [
            'pending_amount' => (string) (clone $pendingQuery)->sum('original_amount'),
            'pending_count' => (clone $pendingQuery)->count(),
            'card_amount' => (string) (clone $cardQuery)->sum('original_amount'),
            'card_count' => (clone $cardQuery)->count(),
            'agree_amount' => (string) (clone $agreeQuery)->sum('original_amount'),
            'agree_count' => (clone $agreeQuery)->count(),
            'paying_amount' => (string) (clone $payingQuery)->sum('original_amount'),
            'paying_count' => (clone $payingQuery)->count(),
            'paid_today_amount' => (string) (clone $paidTodayQuery)->sum('original_amount'),
            'paid_today_count' => (clone $paidTodayQuery)->count(),
            'fail_amount' => (string) (clone $failQuery)->sum('original_amount'),
            'fail_count' => (clone $failQuery)->count(),
        ];
    }

    /**
     * @param array<int, string> $admins
     * @param array<int, string> $leaders
     * @param array<int, int> $withdrawCounts
     * @param array<int, int> $todayCounts
     * @return array<string, mixed>
     */
    private function formatItem(
        WithdrawOrderModel $row,
        bool $detail = false,
        array $admins = [],
        array $leaders = [],
        array $withdrawCounts = [],
        array $todayCounts = []
    ): array {
        $user = $row->user;
        $status = (int) $row->status;
        $uid = (int) $row->user_id;
        $leaderId = (int) ($user?->leader1 ?? 0);
        $auditAdminId = (int) ($row->audit_admin_id ?: $row->admin_id);
        $payoutAdminId = (int) ($row->payout_admin_id ?? 0);
        $lockedBy = (int) ($row->locked_by ?? 0);

        $risk = $this->buildRisk($row, $user, (int) ($withdrawCounts[$uid] ?? 0), (int) ($todayCounts[$uid] ?? 0));

        $payeeAccount = (string) ($row->withdrawal_address ?: $row->bank_number ?: $row->document_id ?: '');
        $typeText = WithdrawOrderModel::$typeMap[$row->type] ?? '未知';
        $coin = (string) ($row->coin ?: ($row->type == WithdrawOrderModel::TYPE_CRYPTO ? 'USDT' : 'CNY'));

        $data = [
            'id' => (int) $row->id,
            'order_no' => (string) $row->order_no,
            'user_id' => $uid,
            'username' => (string) ($user?->username ?? ''),
            'nickname' => (string) ($user?->nickname ?? ''),
            'mobile' => (string) ($user?->mobile ?? ''),
            'level_id' => (int) ($user?->level_id ?? 0),
            'user_balance' => (string) ($user?->balance ?? '0'),
            'total_recharge' => (string) ($user?->total_recharge ?? '0'),
            'total_withdraw' => (string) ($user?->total_withdraw ?? '0'),
            'withdraw_times' => (int) ($withdrawCounts[$uid] ?? 0),
            'leader_id' => $leaderId,
            'leader_label' => $leaders[$leaderId] ?? ($leaderId > 0 ? (string) $leaderId : '-'),
            'is_virtual' => (int) ($row->is_virtual ?? 0),
            'original_amount' => (string) $row->original_amount,
            'withdraw_amount' => (string) $row->withdraw_amount,
            'handling_fee' => (string) ($row->handling_fee ?? '0'),
            'type' => (int) $row->type,
            'type_text' => $typeText,
            'coin' => $coin,
            'channel' => (string) ($row->channel ?? ''),
            'channel_text' => $this->channelText($row),
            'true_name' => (string) ($row->true_name ?: $user?->real_name ?: '-'),
            'bank_name' => (string) ($row->bank_name ?: $typeText),
            'bank_number' => (string) ($row->bank_number ?? ''),
            'withdrawal_address' => (string) ($row->withdrawal_address ?? ''),
            'payee_account' => $payeeAccount,
            'status' => $status,
            'status_text' => WithdrawOrderModel::$statusMap[$status] ?? '未知',
            'audit_status_text' => $this->auditStatusText($status),
            'payout_status_text' => $this->payoutStatusText($status),
            'audit_admin_id' => $auditAdminId,
            'audit_admin_name' => $admins[$auditAdminId] ?? ($auditAdminId > 0 ? (string) $auditAdminId : '系统'),
            'payout_admin_id' => $payoutAdminId,
            'payout_admin_name' => $admins[$payoutAdminId] ?? ($payoutAdminId > 0 ? (string) $payoutAdminId : '-'),
            'locked_by' => $lockedBy,
            'locked_by_name' => $admins[$lockedBy] ?? '',
            'lock_text' => $lockedBy > 0
                ? (($admins[$lockedBy] ?? ('admin#' . $lockedBy)) . ' 锁单中')
                : ($status === WithdrawOrderModel::STATUS_SUCCESS ? '已完成' : '未锁单'),
            'card_reason' => (string) ($row->card_reason ?? ''),
            'remark' => (string) ($row->remark ?? ''),
            'ip' => (string) ($row->ip ?? ''),
            'ip_area' => IpLocationResolver::resolve((string) ($row->ip ?? '')),
            'risk_score' => $risk['score'],
            'risk_tags' => $risk['tags'],
            'risk_advice' => $risk['advice'],
            'created_at' => (string) $row->created_at,
            'checked_at' => ((int) ($row->checked_at ?? 0)) > 0 ? date('Y-m-d H:i:s', (int) $row->checked_at) : '',
            'paid_at' => ((int) ($row->paid_at ?? 0)) > 0 ? date('Y-m-d H:i:s', (int) $row->paid_at) : '',
        ];

        if ($detail) {
            $data['user'] = $user?->toArray();
        }

        return $data;
    }

    /** @return array{score:int, tags:list<string>, advice:string} */
    private function buildRisk(WithdrawOrderModel $row, ?UserModel $user, int $withdrawTimes, int $todayTimes): array
    {
        $tags = [];
        $score = 20;
        $amount = (float) $row->original_amount;
        $balance = (float) ($user?->balance ?? 0);
        $totalRecharge = (float) ($user?->total_recharge ?? 0);
        $totalWithdraw = (float) ($user?->total_withdraw ?? 0);

        if ($withdrawTimes <= 1) {
            $tags[] = '首次提现';
            $score += 15;
        }
        if ($todayTimes >= 2) {
            $tags[] = '今日多次提现';
            $score += 20;
        }
        if ($balance > 0 && $amount >= $balance * 0.9) {
            $tags[] = '余额清仓';
            $score += 18;
        }
        if ($totalWithdraw + $amount >= $totalRecharge && $totalRecharge > 0) {
            $tags[] = '提现大于充值';
            $score += 15;
        }
        if ((int) $row->status === WithdrawOrderModel::STATUS_CARD_REVIEW) {
            $tags[] = '卡审核';
            $score += 10;
        }
        if ((int) $row->type === WithdrawOrderModel::TYPE_CRYPTO) {
            $tags[] = 'USDT地址提现';
            $score += 8;
        }

        $area = IpLocationResolver::resolve((string) ($row->ip ?? ''));
        if ($area !== '' && !str_contains($area, '中国') && !str_contains($area, '局域网')) {
            $tags[] = ($area !== '' ? explode(' ', $area)[0] : '境外') . 'IP';
            $score += 12;
        }

        $leaderId = (int) ($user?->leader1 ?? 0);
        if ($leaderId > 0) {
            $recentSameLeader = WithdrawOrderModel::query()
                ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 86400))
                ->whereIn('status', [
                    WithdrawOrderModel::STATUS_PENDING,
                    WithdrawOrderModel::STATUS_WAIT,
                    WithdrawOrderModel::STATUS_CARD_REVIEW,
                    WithdrawOrderModel::STATUS_AGREE,
                    WithdrawOrderModel::STATUS_PAYING,
                ])
                ->whereHas('user', static fn ($q) => $q->where('leader1', $leaderId))
                ->count();
            if ($recentSameLeader >= 3) {
                $tags[] = '代理线集中提现';
                $score += 20;
            }
        }

        $lastLoginAt = (int) ($user?->last_login_at ?? 0);
        if ($lastLoginAt > 0 && (time() - $lastLoginAt) > 7 * 86400) {
            $tags[] = '多日未交易';
            $score += 10;
        }

        $score = min(99, $score);
        $advice = $score >= 75 ? '建议卡审核' : ($score >= 60 ? '建议人工复核' : '可正常审核');

        return ['score' => $score, 'tags' => array_values(array_unique($tags)), 'advice' => $advice];
    }

    private function auditStatusText(int $status): string
    {
        return match ($status) {
            WithdrawOrderModel::STATUS_CARD_REVIEW => '卡审核',
            WithdrawOrderModel::STATUS_REJECT => '审核失败',
            WithdrawOrderModel::STATUS_AGREE,
            WithdrawOrderModel::STATUS_PAYING,
            WithdrawOrderModel::STATUS_SUCCESS,
            WithdrawOrderModel::STATUS_FAIL,
            WithdrawOrderModel::STATUS_OFFLINE_FAIL => '已审核',
            default => '待审核',
        };
    }

    private function payoutStatusText(int $status): string
    {
        return match ($status) {
            WithdrawOrderModel::STATUS_AGREE => '待打款',
            WithdrawOrderModel::STATUS_PAYING => '出款中',
            WithdrawOrderModel::STATUS_SUCCESS => '已出款',
            WithdrawOrderModel::STATUS_FAIL,
            WithdrawOrderModel::STATUS_OFFLINE_FAIL => '出款失败',
            default => '待打款',
        };
    }

    private function channelText(WithdrawOrderModel $row): string
    {
        if ((int) $row->type === WithdrawOrderModel::TYPE_CRYPTO) {
            $coin = strtoupper((string) ($row->coin ?: 'USDT'));
            return $coin . '-TRC20';
        }
        return WithdrawOrderModel::$typeMap[$row->type] ?? '未知';
    }

    /** @param list<int> $ids @return array<int, string> */
    private function adminNameMap(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        return AdminModel::whereIn('id', $ids)
            ->get(['id', 'username', 'nickname'])
            ->mapWithKeys(static function (AdminModel $admin) {
                $name = (string) ($admin->nickname ?: $admin->username);
                return [(int) $admin->id => $name];
            })
            ->all();
    }

    /** @param list<int> $ids @return array<int, string> */
    private function userLabelMap(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        return UserModel::whereIn('id', $ids)
            ->get(['id', 'username', 'remark'])
            ->mapWithKeys(static function (UserModel $user) {
                return [(int) $user->id => UserModel::displayLabel($user, (int) $user->id)];
            })
            ->all();
    }

    /** @param list<int> $userIds @return array<int, int> */
    private function withdrawCountMap(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        return WithdrawOrderModel::query()
            ->whereIn('user_id', $userIds)
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id')
            ->map(static fn ($v) => (int) $v)
            ->all();
    }

    /** @param list<int> $userIds @return array<int, int> */
    private function todayWithdrawCountMap(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        return WithdrawOrderModel::query()
            ->whereIn('user_id', $userIds)
            ->where('created_at', '>=', date('Y-m-d 00:00:00'))
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id')
            ->map(static fn ($v) => (int) $v)
            ->all();
    }

    private function wasFrozenOnApply(WithdrawOrderModel $order): bool
    {
        return UserWaterModel::where('order_no', (int) $order->id)
            ->where('type', 4)
            ->where('detail_type', 2)
            ->exists();
    }

    private function freezeUserBalance(WithdrawOrderModel $order): void
    {
        $user = UserModel::where('id', $order->user_id)->lockForUpdate()->first();
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $amount = (string) $order->original_amount;
        if (bccomp((string) $user->balance, $amount, 4) < 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '用户余额不足');
        }

        $before = (string) $user->balance;
        $after = bcsub($before, $amount, 4);
        $frozenAfter = bcadd((string) $user->frozen_balance, $amount, 4);

        UserModel::where('id', (int) $user->id)->update([
            'balance' => $after,
            'frozen_balance' => $frozenAfter,
        ]);

        UserWaterModel::create([
            'user_id'        => (int) $order->user_id,
            'admin_id'       => (int) $order->admin_id,
            'order_no'       => (int) $order->id,
            'title'          => '提现冻结',
            'type'           => 4,
            'detail_type'    => 2,
            'direction'      => 2,
            'amount'         => $amount,
            'before_balance' => $before,
            'after_balance'  => $after,
            'remark'         => '提现审核冻结',
        ]);
    }

    private function refundUserBalance(WithdrawOrderModel $order): void
    {
        $user = UserModel::where('id', $order->user_id)->lockForUpdate()->first();
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $amount = (string) $order->original_amount;
        $before = (string) $user->balance;
        $after = bcadd($before, $amount, 4);
        $frozenBefore = (string) $user->frozen_balance;
        $frozenAfter = bccomp($frozenBefore, $amount, 4) >= 0
            ? bcsub($frozenBefore, $amount, 4)
            : '0';

        UserModel::where('id', (int) $user->id)->update([
            'balance' => $after,
            'frozen_balance' => $frozenAfter,
        ]);

        UserWaterModel::create([
            'user_id'        => (int) $order->user_id,
            'admin_id'       => (int) $order->admin_id,
            'order_no'       => (int) $order->id,
            'title'          => '提现退回',
            'type'           => 1,
            'detail_type'    => 2,
            'direction'      => 1,
            'amount'         => $amount,
            'before_balance' => $before,
            'after_balance'  => $after,
            'remark'         => '提现审核驳回退回',
        ]);
    }

    private function confirmPayout(WithdrawOrderModel $order): void
    {
        $user = UserModel::where('id', $order->user_id)->lockForUpdate()->first();
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $amount = (string) $order->original_amount;
        $frozenBefore = (string) $user->frozen_balance;
        $frozenAfter = bccomp($frozenBefore, $amount, 4) >= 0
            ? bcsub($frozenBefore, $amount, 4)
            : '0';
        $totalWithdraw = bcadd((string) $user->total_withdraw, $amount, 4);

        UserModel::where('id', (int) $user->id)->update([
            'frozen_balance' => $frozenAfter,
            'total_withdraw' => $totalWithdraw,
        ]);
    }
}
