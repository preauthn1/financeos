<?php

declare(strict_types=1);

namespace app\service\api;

use app\model\ad\AdProductOrderModel;
use app\model\member\UserModel;

class FinanceProductInvestRateService
{
    public function resolveRate(array $level, int $userId): float
    {
        $rate = (float) ($level['invest_rate'] ?? 30);
        $activeRate = (float) ($level['active_rate'] ?? 0);
        $maxRate = (float) ($level['invest_max_rate'] ?? $rate);
        if ($activeRate <= 0) {
            return $rate;
        }

        $teamIds = UserModel::where('leader1', $userId)->where('is_virtual', 0)->pluck('id')->all();
        $activeCount = $this->countYesterdayBuyers($teamIds);
        $boosted = $rate + $activeCount * $activeRate;

        return min($boosted, max($maxRate, $rate));
    }

    /** @param array<int,int> $userIds */
    private function countYesterdayBuyers(array $userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        $start = strtotime(date('Y-m-d', strtotime('-1 day')) . ' 00:00:00');
        $end = strtotime(date('Y-m-d', strtotime('-1 day')) . ' 23:59:59');

        return AdProductOrderModel::whereIn('user_id', $userIds)
            ->whereBetween('matched_at', [$start, $end])
            ->whereIn('status', [AdProductOrderModel::STATUS_PURCHASED, AdProductOrderModel::STATUS_SETTLED])
            ->distinct('user_id')
            ->count('user_id');
    }
}
