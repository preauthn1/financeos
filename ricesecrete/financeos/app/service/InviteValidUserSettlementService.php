<?php

declare(strict_types=1);

namespace app\service;

use app\model\activity\ActivityInviteTierModel;
use app\model\activity\ActivityModel;
use app\model\activity\ActivityRecordModel;
use app\model\member\UserModel;
use app\model\pay\RechargeOrderModel;
use support\Log;

class InviteValidUserSettlementService
{
    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    /**
     * 每日结算邀请有效用户奖励（默认 20:00）
     */
    public function settle(): int
    {
        $activity = ActivityModel::where('type', ActivityModel::TYPE_INVITE_VALID)
            ->where('status', 1)->orderByDesc('id')->first();
        if (!$activity) {
            return 0;
        }

        $extra = is_array($activity->extra) ? $activity->extra : [];
        $settleTime = (string) ($extra['settle_time'] ?? '20:00');
        if (!$this->shouldRunNow($settleTime)) {
            return 0;
        }

        $today = date('Ymd');
        $cacheKey = 'invite_valid_settled:' . $activity->id . ':' . $today;
        if (\support\Redis::get($cacheKey)) {
            return 0;
        }

        $baseInviteNum = (int) ($extra['base_invite_num'] ?? 3);
        $baseReward = (string) ($extra['base_reward'] ?? $activity->reward);
        $globalMultiplier = (float) ($extra['global_multiplier'] ?? 1);

        $leaders = UserModel::query()->where('leader1', '>', 0)->distinct()->pluck('leader1');
        $count = 0;

        foreach ($leaders->unique() as $leaderId) {
            try {
                $validCount = $this->countTodayValidInvites((int) $leaderId);
                if ($validCount < $baseInviteNum) {
                    continue;
                }

                $reward = bcmul(bcmul($baseReward, (string) $globalMultiplier, 4), '1', 4);
                $multiplier = $this->resolveTierMultiplier((int) $activity->id, $validCount, (int) $leaderId);

                ActivityRecordModel::firstOrCreate(
                    [
                        'user_id' => $leaderId,
                        'activity_id' => $activity->id,
                        'settle_date' => $today,
                        'from_user_id' => 0,
                    ],
                    [
                        'reward' => $reward,
                        'reward_multiplier' => $multiplier,
                        'status' => 0,
                    ]
                );
                $count++;
            } catch (\Throwable $e) {
                Log::error('invite valid settle failed: ' . $e->getMessage(), ['leader' => $leaderId]);
            }
        }

        \support\Redis::setex($cacheKey, 86400, '1');

        return $count;
    }

    private function countTodayValidInvites(int $leaderId): int
    {
        $todayStart = strtotime(date('Y-m-d'));
        $subs = UserModel::where('leader1', $leaderId)
            ->where('registered_at', '>=', $todayStart)
            ->get(['id', 'registered_at']);

        $count = 0;
        foreach ($subs as $sub) {
            $firstRecharge = RechargeOrderModel::where('user_id', $sub->id)
                ->where('status', RechargeOrderModel::STATUS_SUCCESS)
                ->orderBy('succeeded_at')
                ->first();
            if (!$firstRecharge) {
                continue;
            }
            $regAt = (int) $sub->registered_at;
            $rechargeAt = (int) $firstRecharge->succeeded_at;
            if ($rechargeAt > 0 && $rechargeAt - $regAt <= 86400) {
                $count++;
            }
        }

        return $count;
    }

    private function resolveTierMultiplier(int $activityId, int $validCount, int $leaderId): float
    {
        $tiers = ActivityInviteTierModel::where('activity_id', $activityId)
            ->where('status', 1)
            ->where('invite_num', '<=', $validCount)
            ->orderByDesc('invite_num')
            ->get();

        foreach ($tiers as $tier) {
            $rate = (float) $tier->winner_rate;
            if ($rate <= 0) {
                continue;
            }
            $seed = crc32($leaderId . ':' . date('Ymd') . ':' . $tier->id);
            if (($seed % 100) < $rate) {
                return (float) $tier->multiplier;
            }
        }

        return 1.0;
    }

    private function shouldRunNow(string $settleTime): bool
    {
        $now = date('H:i');
        $target = substr($settleTime, 0, 5);

        return $now === $target;
    }
}
