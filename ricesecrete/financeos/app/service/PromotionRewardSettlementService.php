<?php

declare(strict_types=1);

namespace app\service;

use app\model\ad\AdProductOrderModel;
use app\model\member\PromotionRewardDayModel;
use app\model\member\UserModel;
use support\Log;
use support\Redis;

class PromotionRewardSettlementService
{
    private const STREAK_KEY_PREFIX = 'promotion_streak:';

    private const LAST_DATE_KEY_PREFIX = 'promotion_last_date:';

    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    /**
     * 扫描今日有效推广下级，为上级累计连续推广奖励
     */
    public function addRewards(): int
    {
        $date = date('Y-m-d');
        $todayStart = strtotime($date . ' 00:00:00');
        $todayEnd = strtotime($date . ' 23:59:59');
        $minRecharge = (float) $this->settingService->getValue('recharge_num_valid_user', 0);

        $validUserIds = AdProductOrderModel::query()
            ->whereIn('status', [AdProductOrderModel::STATUS_BOUGHT, AdProductOrderModel::STATUS_SETTLED])
            ->where('level_id', '>', 0)
            ->pluck('user_id')
            ->unique();

        if ($validUserIds->isEmpty()) {
            return 0;
        }

        $leaders = UserModel::query()
            ->where('leader1', '>', 0)
            ->where('total_recharge', '>=', $minRecharge)
            ->where('registered_at', '>=', $todayStart)
            ->where('registered_at', '<=', $todayEnd)
            ->whereIn('id', $validUserIds)
            ->pluck('leader1')
            ->unique()
            ->values();

        $count = 0;
        foreach ($leaders as $leaderId) {
            try {
                if ($this->addLeaderReward((int) $leaderId, $date)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::error('promotion reward failed: ' . $e->getMessage(), ['leader_id' => $leaderId]);
            }
        }

        return $count;
    }

    private function addLeaderReward(int $leaderId, string $date): bool
    {
        $lockKey = 'promotion_reward_lock_' . $date . ':' . $leaderId;
        if (!Redis::setNx($lockKey, '1', 86400)) {
            return false;
        }

        $lastDate = Redis::get(self::LAST_DATE_KEY_PREFIX . $leaderId);
        if ($lastDate === $date) {
            return false;
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $streak = ($lastDate === $yesterday)
            ? (int) (Redis::get(self::STREAK_KEY_PREFIX . $leaderId) ?? 0) + 1
            : 1;

        Redis::setex(self::LAST_DATE_KEY_PREFIX . $leaderId, 86400 * 2, $date);
        Redis::setex(self::STREAK_KEY_PREFIX . $leaderId, 86400 * 30, (string) $streak);

        $milestone = $this->resolveMilestone($streak);
        if ($milestone === null) {
            return true;
        }

        $day = (int) $milestone['day'];
        if (PromotionRewardDayModel::where('user_id', $leaderId)->where('day', $day)->exists()) {
            return false;
        }

        PromotionRewardDayModel::create([
            'user_id' => $leaderId,
            'day'     => $day,
            'reward'  => $milestone['reward'],
            'status'  => 0,
        ]);

        return true;
    }

    /**
     * @return array{day: int, reward: float|string}|null
     */
    private function resolveMilestone(int $streak): ?array
    {
        $config = $this->settingService->getValue('promotion_reward_days', [
            ['day' => 1, 'reward' => 10],
            ['day' => 3, 'reward' => 30],
            ['day' => 7, 'reward' => 100],
        ]);

        if (!is_array($config)) {
            return null;
        }

        foreach ($config as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((int) ($item['day'] ?? 0) === $streak) {
                return [
                    'day'    => $streak,
                    'reward' => $item['reward'] ?? 0,
                ];
            }
        }

        return null;
    }
}
