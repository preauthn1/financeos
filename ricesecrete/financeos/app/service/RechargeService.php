<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\activity\PddTurntableCountModel;
use app\model\ad\AdPlanModel;
use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\model\pay\PayPlatformModel;
use app\model\pay\RechargeOrderModel;
use app\support\DateTimeFormat;
use app\support\ErrorCode;
use app\support\UserWaterType;
use Illuminate\Database\Capsule\Manager as DB;

class RechargeService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = RechargeOrderModel::query()->with(['user:id,username,nickname,real_name,is_virtual,leader1', 'platform:id,name,show_name']);

        $this->applyFilters($query, $filters);

        $kpis = $this->buildKpis(clone $query);

        $total = (clone $query)->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn (RechargeOrderModel $row) => $this->formatItem($row));

        return ['total' => $total, 'items' => $items, 'kpis' => $kpis];
    }

    public function show(int $id): array
    {
        $order = RechargeOrderModel::with(['user', 'platform'])->find($id);
        if (!$order) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '充值订单不存在');
        }
        return $this->formatItem($order, true);
    }

    public function audit(int $id, int $status, string $remark, int $adminId = 0): array
    {
        if (!isset(RechargeOrderModel::$statusMap[$status]) || !in_array($status, [RechargeOrderModel::STATUS_SUCCESS, RechargeOrderModel::STATUS_FAIL], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '审核状态有误');
        }
        if ($remark === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '备注不能为空');
        }

        $order = DB::transaction(function () use ($id, $status, $remark, $adminId) {
            $order = RechargeOrderModel::where('id', $id)
                ->where('status', RechargeOrderModel::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '充值订单不存在或状态不可审核');
            }

            $order->status = $status;
            $order->remark = $remark;
            $order->admin_id = $adminId;

            if ($status === RechargeOrderModel::STATUS_SUCCESS) {
                $isFirst = $this->isFirstRecharge((int) $order->user_id);
                $order->paid_at = time();
                $order->succeeded_at = time();
                $order->is_first = $isFirst ? 1 : 0;
                $order->save();

                $this->creditUser((int) $order->user_id, (string) $order->money, (int) $order->id, $adminId);
                $this->onRechargeSuccess($order, $isFirst);
            } else {
                $order->save();
            }

            return $order->fresh(['user', 'platform']);
        });

        return $this->formatItem($order, true);
    }

    public function manualCreate(array $data, int $adminId = 0, string $ip = ''): array
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $money = (string) ($data['money'] ?? '0');
        if ($userId <= 0 || (float) $money <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '用户ID和金额必填');
        }

        $user = UserModel::find($userId);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $payId = (int) ($data['pay_platform_id'] ?? 0);
        $platform = $payId > 0 ? PayPlatformModel::find($payId) : null;

        return DB::transaction(function () use ($data, $user, $money, $payId, $platform, $adminId, $ip) {
            $isFirst = $this->isFirstRecharge((int) $user->id);
            $order = RechargeOrderModel::create([
                'order_no'       => $this->generateOrderId(),
                'user_id'        => $user->id,
                'is_virtual'     => (int) $user->is_virtual,
                'money'          => $money,
                'original_amount' => $money,
                'exchange_rate'  => $platform?->exchange_rate ?? 1,
                'type'           => RechargeOrderModel::TYPE_MANUAL,
                'status'         => RechargeOrderModel::STATUS_SUCCESS,
                'pay_platform_id'         => $payId,
                'remark'         => (string) ($data['remark'] ?? '后台手动补单'),
                'paid_at'       => time(),
                'succeeded_at'   => time(),
                'admin_id'       => $adminId,
                'order_source'           => RechargeOrderModel::FROM_ADMIN,
                'ip'             => $ip,
                'is_first'       => $isFirst ? 1 : 0,
                'coin'           => $platform?->coin,
            ]);

            $this->creditUser((int) $user->id, $money, (int) $order->id, $adminId);
            $this->onRechargeSuccess($order, $isFirst);
            return $this->formatItem($order->fresh(['user', 'platform']), true);
        });
    }

    /**
     * 支付回调成功入账（幂等）
     */
    public function notifySuccess(string $orderNo, string $amount = '', string $tradeNo = ''): RechargeOrderModel
    {
        return DB::transaction(function () use ($orderNo, $amount, $tradeNo) {
            $order = RechargeOrderModel::query()
                ->where(function ($q) use ($orderNo) {
                    $q->where('order_no', $orderNo)->orWhere('trade_no', $orderNo);
                })
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '充值订单不存在');
            }

            if ((int) $order->status === RechargeOrderModel::STATUS_SUCCESS) {
                return $order;
            }

            if (!in_array((int) $order->status, [RechargeOrderModel::STATUS_PENDING, RechargeOrderModel::STATUS_WAIT], true)) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '订单状态不可入账');
            }

            if ($amount !== '' && bccomp($amount, (string) $order->money, 2) !== 0) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '回调金额与订单不符');
            }

            if ($tradeNo !== '') {
                $order->trade_no = $tradeNo;
            }

            $order->status = RechargeOrderModel::STATUS_SUCCESS;
            $order->paid_at = time();
            $order->succeeded_at = time();
            $isFirst = $this->isFirstRecharge((int) $order->user_id);
            $order->is_first = $isFirst ? 1 : 0;
            $order->save();

            $this->creditUser((int) $order->user_id, (string) $order->money, (int) $order->id, 0);
            $this->onRechargeSuccess($order, $isFirst);

            return $order->fresh(['user', 'platform']);
        });
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['order_no'])) {
            $query->where('order_no', 'like', '%' . $filters['order_no'] . '%');
        }
        if (!empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('order_no', 'like', "%{$kw}%")
                    ->orWhere('trade_no', 'like', "%{$kw}%")
                    ->orWhereHas('user', function ($uq) use ($kw) {
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
        if (!empty($filters['pay_platform_id'])) {
            $query->where('pay_platform_id', (int) $filters['pay_platform_id']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }
    }

    private function buildKpis($query): array
    {
        $base = clone $query;
        $todayStart = date('Y-m-d 00:00:00');

        return [
            'total_count'        => (clone $base)->count(),
            'pending_count'      => (clone $base)->where('status', RechargeOrderModel::STATUS_PENDING)->count(),
            'success_count'      => (clone $base)->where('status', RechargeOrderModel::STATUS_SUCCESS)->count(),
            'today_count'        => (clone $base)->where('created_at', '>=', $todayStart)->count(),
            'today_amount'       => (string) ((clone $base)->where('status', RechargeOrderModel::STATUS_SUCCESS)->where('created_at', '>=', $todayStart)->sum('money')),
            'success_amount'     => (string) ((clone $base)->where('status', RechargeOrderModel::STATUS_SUCCESS)->sum('money')),
        ];
    }

    private function formatItem(RechargeOrderModel $row, bool $detail = false): array
    {
        $data = $row->toArray();
        $data['status_text'] = RechargeOrderModel::$statusMap[$row->status] ?? '未知';
        $data['type_text'] = RechargeOrderModel::$typeMap[$row->type] ?? '未知';
        $data['channel_name'] = $row->platform?->show_name ?: $row->platform?->name ?: '-';
        $data['paid_at_text'] = DateTimeFormat::datetime($row->paid_at);
        $data['succeeded_at_text'] = DateTimeFormat::datetime($row->succeeded_at);
        $data['voucher_urls'] = array_values(array_filter(array_map('trim', explode(';', (string) $row->voucher_url))));
        if ($row->user) {
            $data['username'] = $row->user->username;
            $data['nickname'] = $row->user->nickname;
            $data['real_name'] = $row->user->real_name;
        }
        if ($detail) {
            $data['user'] = $row->user?->toArray();
            $data['platform'] = $row->platform?->toArray();
            $channel = json_decode((string) $row->result, true);
            $data['offline_channel'] = is_array($channel) ? $channel : null;
        }
        return $data;
    }

    private function isFirstRecharge(int $userId): bool
    {
        return !RechargeOrderModel::where('user_id', $userId)
            ->where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->where('order_source', RechargeOrderModel::FROM_FRONT)
            ->exists();
    }

    private function creditUser(int $userId, string $money, int $orderId, int $adminId): void
    {
        $user = UserModel::where('id', $userId)->lockForUpdate()->first();
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $amount = (string) $money;
        $beforeBalance = (string) $user->balance;
        $beforeRecharge = (string) $user->recharge_balance;

        // 实际可用余额进入基础钱包；充值钱包仅累计充值额（不可消费）
        $user->balance = bcadd($beforeBalance, $amount, 4);
        $user->recharge_balance = bcadd($beforeRecharge, $amount, 4);
        $user->total_recharge = bcadd((string) $user->total_recharge, $amount, 4);
        $user->save();

        UserWaterModel::create([
            'user_id' => $userId,
            'admin_id' => $adminId,
            'order_no' => $orderId,
            'title' => '用户充值',
            'type' => UserWaterType::TYPE_USER,
            'detail_type' => 1,
            'direction' => 1,
            'amount' => $amount,
            'before_balance' => $beforeBalance,
            'after_balance' => (string) $user->balance,
            'remark' => '充值到账-基础钱包',
        ]);

        UserWaterModel::create([
            'user_id' => $userId,
            'admin_id' => $adminId,
            'order_no' => $orderId,
            'title' => '累计充值记录',
            'type' => UserWaterType::TYPE_RECHARGE,
            'detail_type' => 1,
            'direction' => 1,
            'amount' => $amount,
            'before_balance' => $beforeRecharge,
            'after_balance' => (string) $user->recharge_balance,
            'remark' => '充值累计（非可用余额）',
        ]);
    }

    private function generateOrderId(): string
    {
        return 'RC' . date('YmdHis') . random_int(1000, 9999);
    }

    private function onRechargeSuccess(RechargeOrderModel $order, bool $isFirst): void
    {
        $userId = (int) $order->user_id;
        $amount = (string) $order->money;

        try {
            AdPlanModel::updateUserPlanLevel($userId);
            PddTurntableCountModel::addLeader1Count($userId, (float) $amount);
            (new RechargeBonusCommissionService())->onRechargeSuccess($userId, $amount, (int) $order->id);
            (new ActiveUserService())->onUserRecharged($userId);
            if ($isFirst) {
                $this->grantFirstChargeRebate($order);
            }
        } catch (\Throwable) {
            // 充值主流程已成功，副作用失败不阻断
        }
    }

    private function grantFirstChargeRebate(RechargeOrderModel $order): void
    {
        $setting = new SettingService();
        if ((string) $setting->getValue('is_open_first_charge_rebate', '0') !== '1') {
            return;
        }

        $user = UserModel::find((int) $order->user_id);
        if (!$user || !$user->created_at) {
            return;
        }

        $userDate = date('Y-m-d', strtotime((string) $user->created_at));
        $orderDate = date('Y-m-d', strtotime((string) $order->created_at));
        if ($userDate !== $orderDate) {
            return;
        }

        $rebate = $setting->getValue('first_charge_rebate', []);
        if (!is_array($rebate)) {
            $rebate = [];
        }
        $min = (float) ($rebate['min'] ?? $setting->getValue('first_charge_rebate_min', 0));
        $max = (float) ($rebate['max'] ?? $setting->getValue('first_charge_rebate_max', 0));
        $percent = (float) ($rebate['percent'] ?? $setting->getValue('first_charge_rebate_percent', 0));
        $cap = (float) ($rebate['cap'] ?? $setting->getValue('first_charge_rebate_cap', 0));
        $amount = (float) $order->money;

        if ($percent <= 0 || $amount < $min || ($max > 0 && $amount > $max)) {
            return;
        }

        $gift = $amount * $percent / 100;
        if ($cap > 0 && $gift > $cap) {
            $gift = $cap;
        }
        if ($gift <= 0) {
            return;
        }

        $giftStr = number_format($gift, 4, '.', '');
        $before = (string) $user->balance;
        $user->balance = bcadd($before, $giftStr, 4);
        $user->save();

        UserWaterModel::create([
            'user_id' => (int) $user->id,
            'admin_id' => (int) $order->admin_id,
            'order_no' => (int) $order->id,
            'title' => '首充送款',
            'type' => UserWaterType::TYPE_USER,
            'detail_type' => 26,
            'direction' => 1,
            'amount' => $giftStr,
            'before_balance' => $before,
            'after_balance' => (string) $user->balance,
            'remark' => '首充送款：' . $giftStr,
        ]);
    }
}
