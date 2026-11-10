<?php

declare(strict_types=1);

namespace app\service;

use app\model\ad\AdProductOrderModel;
use app\model\member\TaskSummaryV2Model;
use app\model\member\UserModel;
use app\model\pay\RechargeOrderModel;

/**
 * 团队活跃用户判定（巴西盘规则）
 */
class ActiveUserService
{
    /**
     * 用户指定日期交易是否计入团队活跃
     */
    public function countsAsActiveOnDate(int $userId, string $dateYmd): bool
    {
        $user = UserModel::find($userId);
        if (!$user) {
            return false;
        }

        if ((int) $user->trial_no_recharge === 1) {
            $hasRecharge = RechargeOrderModel::where('user_id', $userId)
                ->where('status', RechargeOrderModel::STATUS_SUCCESS)
                ->exists();
            if (!$hasRecharge) {
                return false;
            }
        }

        $summary = TaskSummaryV2Model::where('user_id', $userId)->where('date', $dateYmd)->first();
        if ($summary && ((int) $summary->task_level1 + (int) $summary->task_level2 + (int) $summary->task_level3) > 0) {
            return true;
        }

        $dayStart = strtotime(substr($dateYmd, 0, 4) . '-' . substr($dateYmd, 4, 2) . '-' . substr($dateYmd, 6, 2));
        $dayEnd = $dayStart + 86400;

        return AdProductOrderModel::where('user_id', $userId)
            ->whereIn('status', [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED])
            ->where('created_at', '>=', $dayStart)
            ->where('created_at', '<', $dayEnd)
            ->exists();
    }

    /**
     * 统计团队今日活跃人数（含体验区）
     */
    public function countTeamActiveToday(int $leaderId): int
    {
        return $this->countTeamActiveForDate($leaderId, date('Ymd'));
    }

    /**
     * 统计团队指定日期活跃人数
     */
    public function countTeamActiveForDate(int $leaderId, string $dateYmd): int
    {
        $teamIds = UserModel::query()
            ->where('tree_id', 'like', "%/{$leaderId}/%")
            ->where('tree_id', 'not like', '%/' . $leaderId . '/')
            ->pluck('id');

        if ($teamIds->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach ($teamIds as $uid) {
            if ($this->countsAsActiveOnDate((int) $uid, $dateYmd)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 用户今日交易是否计入团队活跃
     */
    public function countsAsActiveToday(int $userId): bool
    {
        return $this->countsAsActiveOnDate($userId, date('Ymd'));
    }

    /**
     * 充值成功后清除「领券未充值」标记
     */
    public function onUserRecharged(int $userId): void
    {
        UserModel::where('id', $userId)->where('trial_no_recharge', 1)->update(['trial_no_recharge' => 0]);
    }
}
