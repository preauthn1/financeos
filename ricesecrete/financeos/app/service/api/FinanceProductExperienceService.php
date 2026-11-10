<?php

declare(strict_types=1);

namespace app\service\api;

use app\model\ad\AdProductOrderModel;

class FinanceProductExperienceService
{
    public function __construct(private ?FinanceProductLevelConfigService $levelService = null)
    {
        $this->levelService = $levelService ?? new FinanceProductLevelConfigService();
    }

    public function getSessionsCount(int $userId): int
    {
        // 与参考一致：按「任务时段」去重计数，不与 level_id=0 订单数叠加（否则会双倍耗尽 session_count）
        $experienceLevelIds = [];
        foreach ($this->levelService->getAllLevels() as $level) {
            if ((int) ($level['type'] ?? 0) === FinanceProductLevelConfigService::TYPE_EXPERIENCE) {
                $experienceLevelIds[] = (int) ($level['level_id'] ?? 0);
            }
        }
        if ($experienceLevelIds === []) {
            return 0;
        }

        $orders = AdProductOrderModel::where('user_id', $userId)
            ->whereIn('level_id', $experienceLevelIds)
            ->whereIn('status', [AdProductOrderModel::STATUS_PURCHASED, AdProductOrderModel::STATUS_SETTLED])
            ->get(['matched_at']);

        $sessions = $this->levelService->getTaskSessions();
        $sessionKeys = [];
        foreach ($orders as $order) {
            if (!(int) $order->matched_at) {
                continue;
            }
            $day = date('Y-m-d', (int) $order->matched_at);
            $time = date('H:i:s', (int) $order->matched_at);
            foreach ($sessions as $session) {
                $start = (string) ($session['started_at'] ?? '00:00:00');
                $end = (string) ($session['ended_at'] ?? '23:59:59');
                if ($time > $start && $time < $end) {
                    $sessionKeys[$day . ' ' . $start . '-' . $end] = 1;
                }
            }
        }

        return count($sessionKeys);
    }
}
