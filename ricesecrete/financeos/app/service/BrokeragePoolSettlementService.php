<?php

declare(strict_types=1);

namespace app\service;

use app\model\member\TaskSummaryV2Model;
use app\model\member\UserBrokeragePoolModel;
use app\model\member\UserModel;
use support\Log;

class BrokeragePoolSettlementService
{
    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    /**
     * 根据团队今日活跃人数更新佣金池可领取额度
     */
    public function settle(): int
    {
        $config = $this->settingService->getValue('brokerage_pool_config', []);
        if (!is_array($config) || $config === []) {
            return 0;
        }

        $userIds = UserModel::query()
            ->where('brokerage_balance', '>', 0)
            ->pluck('id')
            ->merge(UserBrokeragePoolModel::query()->pluck('user_id'))
            ->unique()
            ->values();

        $count = 0;
        foreach ($userIds as $userId) {
            try {
                if ($this->settleUser((int) $userId, $config)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::error('brokerage pool settle failed: ' . $e->getMessage(), ['user_id' => $userId]);
            }
        }

        return $count;
    }

    /**
     * @param array<int, array<string, mixed>> $config
     */
    private function settleUser(int $userId, array $config): bool
    {
        $activeNum = (new ActiveUserService())->countTeamActiveToday($userId);
        $sumAmount = 0.0;
        foreach ($config as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((int) ($item['active_num'] ?? 0) <= $activeNum) {
                $sumAmount += (float) ($item['amount'] ?? 0);
            }
        }

        if ($sumAmount <= 0) {
            return false;
        }

        $pool = UserBrokeragePoolModel::firstOrCreate(
            ['user_id' => $userId],
            ['receivable' => 0, 'received' => 0]
        );

        if ((float) $pool->receivable >= $sumAmount) {
            return false;
        }

        $pool->receivable = $sumAmount;
        $pool->save();

        return true;
    }

    private function countTeamTodayTaskUsers(int $userId): int
    {
        $teamIds = UserModel::query()
            ->where('tree_id', 'like', "%/{$userId}/%")
            ->where('tree_id', 'not like', '%/' . $userId . '/')
            ->pluck('id');

        if ($teamIds->isEmpty()) {
            return 0;
        }

        $today = date('Ymd');

        return TaskSummaryV2Model::query()
            ->whereIn('user_id', $teamIds)
            ->where('date', $today)
            ->whereRaw('(task_level1 + task_level2 + task_level3) > 0')
            ->count();
    }
}
