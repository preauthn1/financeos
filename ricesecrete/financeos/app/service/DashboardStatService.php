<?php

declare(strict_types=1);

namespace app\service;

use app\model\member\UserModel;
use app\model\member\UserWaterModel;
use app\model\pay\RechargeOrderModel;
use app\model\pay\WithdrawOrderModel;

class DashboardStatService
{
    public function overview(): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $yesterdayEnd = date('Y-m-d 23:59:59', strtotime('-1 day'));
        $activeSince = strtotime('-3 days');

        $todayRegister = UserModel::where('is_virtual', 0)
            ->where('created_at', '>=', $todayStart)
            ->count();
        $yesterdayRegister = UserModel::where('is_virtual', 0)
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->count();
        $totalMembers = UserModel::where('is_virtual', 0)->count();

        $todayRechargeAmount = (string) RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('money');
        $todayRechargeCount = RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->distinct('user_id')
            ->count('user_id');
        $totalRechargeAmount = (string) RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)->sum('money');

        $todayWithdrawAmount = (string) WithdrawOrderModel::where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('original_amount');
        $todayWithdrawCount = WithdrawOrderModel::where('status', WithdrawOrderModel::STATUS_SUCCESS)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->distinct('user_id')
            ->count('user_id');
        $totalWithdrawAmount = (string) WithdrawOrderModel::where('status', WithdrawOrderModel::STATUS_SUCCESS)->sum('original_amount');

        $todayFirstRecharge = RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->where('is_first', 1)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->distinct('user_id')
            ->count('user_id');

        $todayRepeatRecharge = RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->where('is_first', 0)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->distinct('user_id')
            ->count('user_id');

        $todayManualRecharge = RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
            ->where('type', RechargeOrderModel::TYPE_MANUAL)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $todayBonusAmount = (string) UserWaterModel::where('type', 3)
            ->where('direction', 1)
            ->where('is_virtual', 0)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $pendingRecharge = RechargeOrderModel::whereIn('status', [
            RechargeOrderModel::STATUS_PENDING,
            RechargeOrderModel::STATUS_WAIT,
        ])->count();
        $pendingWithdraw = WithdrawOrderModel::whereIn('status', [
            WithdrawOrderModel::STATUS_PENDING,
            WithdrawOrderModel::STATUS_WAIT,
            WithdrawOrderModel::STATUS_AGREE,
        ])->count();

        $totalBalance = (string) UserModel::where('is_virtual', 0)->sum('balance');
        $todayActive = UserModel::where('is_virtual', 0)
            ->where('last_login_at', '>=', strtotime($todayStart))
            ->count();
        $active3d = UserModel::where('is_virtual', 0)
            ->where('last_login_at', '>=', $activeSince)
            ->count();

        $totalCommission = (string) UserWaterModel::where('is_virtual', 0)
            ->where('direction', 1)
            ->whereIn('detail_type', [3, 9, 10, 11, 36])
            ->sum('amount');

        return [
            'today' => [
                'register'          => $todayRegister,
                'register_diff'     => $todayRegister - $yesterdayRegister,
                'active'            => $todayActive,
                'recharge_amount'   => $todayRechargeAmount,
                'recharge_count'    => $todayRechargeCount,
                'withdraw_amount'   => $todayWithdrawAmount,
                'withdraw_count'    => $todayWithdrawCount,
                'first_recharge'    => $todayFirstRecharge,
                'repeat_recharge'   => $todayRepeatRecharge,
                'manual_recharge'   => $todayManualRecharge,
                'bonus_amount'      => $todayBonusAmount,
                'rw_diff'           => bcsub($todayRechargeAmount, $todayWithdrawAmount, 2),
            ],
            'yesterday' => [
                'register' => $yesterdayRegister,
            ],
            'total' => [
                'members'           => $totalMembers,
                'recharge_amount'   => $totalRechargeAmount,
                'withdraw_amount'   => $totalWithdrawAmount,
                'balance'           => $totalBalance,
                'commission'        => $totalCommission,
                'rw_diff'           => bcsub($totalRechargeAmount, $totalWithdrawAmount, 2),
            ],
            'pending' => [
                'recharge' => $pendingRecharge,
                'withdraw' => $pendingWithdraw,
            ],
            'online' => [
                'active_3d' => $active3d,
            ],
        ];
    }

    public function trend(int $days = 7): array
    {
        $days = max(1, min(30, $days));
        $items = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $start = $date . ' 00:00:00';
            $end = $date . ' 23:59:59';

            $recharge = (float) RechargeOrderModel::where('status', RechargeOrderModel::STATUS_SUCCESS)
                ->whereBetween('created_at', [$start, $end])
                ->sum('money');
            $withdraw = (float) WithdrawOrderModel::where('status', WithdrawOrderModel::STATUS_SUCCESS)
                ->whereBetween('created_at', [$start, $end])
                ->sum('original_amount');
            $register = UserModel::whereBetween('created_at', [$start, $end])->count();

            $items[] = [
                'date'     => $date,
                'recharge' => $recharge,
                'withdraw' => $withdraw,
                'register' => $register,
            ];
        }

        return ['items' => $items];
    }

    public function alerts(): array
    {
        $pendingWithdraw = WithdrawOrderModel::with('user:id,username')
            ->whereIn('status', [WithdrawOrderModel::STATUS_WAIT, WithdrawOrderModel::STATUS_AGREE])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id'       => $row->id,
                'order_no' => $row->order_no,
                'user_id'  => $row->user_id,
                'username' => $row->user->username ?? '',
                'amount'   => $row->original_amount,
                'status'   => $row->status,
            ]);

        $pendingRecharge = RechargeOrderModel::with('user:id,username')
            ->where('status', RechargeOrderModel::STATUS_PENDING)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id'       => $row->id,
                'order_no' => $row->order_no,
                'user_id'  => $row->user_id,
                'username' => $row->user->username ?? '',
                'amount'   => $row->money,
                'status'   => $row->status,
            ]);

        return [
            'pending_withdraw' => $pendingWithdraw,
            'pending_recharge' => $pendingRecharge,
        ];
    }

    /** @deprecated 兼容旧前端 PanelGroup */
    public function legacyTotal(): array
    {
        $overview = $this->overview();
        return [
            'users'      => $overview['total']['members'],
            'messages'   => $overview['today']['recharge_count'],
            'moneys'     => (int) $overview['today']['recharge_amount'],
            'shoppings'  => $overview['today']['withdraw_count'],
        ];
    }
}
