<?php

declare(strict_types=1);

namespace app\service;

use app\model\activity\ActivityModel;
use app\model\activity\ActivityPasswordModel;
use app\model\activity\ActivityPasswordRecordModel;
use app\model\activity\ActivityRechargeBonusModel;
use app\model\activity\ActivityRecordModel;
use app\model\member\UserModel;
use app\model\pay\RechargeOrderModel;
use app\service\MemberWalletService;
use app\support\UserWaterDetailType;
use Illuminate\Database\Capsule\Manager as DB;
use support\Log;

class RechargeBonusCommissionService
{
    public function __construct(
        private ?MemberWalletService $walletService = null,
    ) {
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    /**
     * 下级领取充值送金奖励后，给上级三级返佣
     */
    public function distribute(int $fromUserId, string $rewardAmount, int $activityId, int $recordId): void
    {
        if (bccomp($rewardAmount, '0', 4) <= 0) {
            return;
        }

        $activity = ActivityModel::find($activityId);
        if (!$activity || (int) $activity->type !== ActivityModel::TYPE_RECHARGE_BONUS) {
            return;
        }

        $extra = is_array($activity->extra) ? $activity->extra : [];
        $rates = $extra['commission_rates'] ?? ['level1' => 0, 'level2' => 0, 'level3' => 0];
        $user = UserModel::find($fromUserId);
        if (!$user) {
            return;
        }

        $leaders = [
            1 => (int) $user->leader1,
            2 => (int) $user->leader2,
            3 => (int) $user->leader3,
        ];

        foreach ($leaders as $level => $leaderId) {
            if ($leaderId <= 0) {
                continue;
            }
            $rate = (float) ($rates['level' . $level] ?? 0);
            if ($rate <= 0) {
                continue;
            }
            $commission = bcmul($rewardAmount, bcdiv((string) $rate, '100', 8), 4);
            if (bccomp($commission, '0', 4) <= 0) {
                continue;
            }
            try {
                $this->walletService->changeBalance(
                    $leaderId,
                    $commission,
                    MemberWalletService::WALLET_BASE,
                    MemberWalletService::DIRECTION_IN,
                    '充值送金下级返佣',
                    UserWaterDetailType::RECHARGE_BONUS_COMMISSION,
                    $recordId,
                    "L{$level}下级{$fromUserId}"
                );
            } catch (\Throwable $e) {
                Log::error('recharge bonus commission failed: ' . $e->getMessage(), [
                    'leader' => $leaderId, 'from' => $fromUserId,
                ]);
            }
        }
    }

    /**
     * 充值回调累计活动充值金额并计算奖励
     */
    public function onRechargeSuccess(int $userId, string $amount, int $orderId): void
    {
        $records = ActivityRecordModel::query()
            ->where('user_id', $userId)
            ->where('status', 0)
            ->where('recharge_bonus_id', '>', 0)
            ->whereHas('activity', fn ($q) => $q->where('type', ActivityModel::TYPE_RECHARGE_BONUS)->where('status', 1))
            ->get();

        foreach ($records as $record) {
            $activity = ActivityModel::find($record->activity_id);
            if (!$activity || !$this->isActivityOpen($activity)) {
                continue;
            }

            $bonus = ActivityRechargeBonusModel::find($record->recharge_bonus_id);
            if (!$bonus || (int) $bonus->status !== 1) {
                continue;
            }

            $newAmount = bcadd((string) $record->recharge_amount, $amount, 2);
            $record->recharge_amount = $newAmount;

            if (bccomp($newAmount, (string) $bonus->amount, 2) >= 0 && bccomp((string) $record->reward, '0', 4) <= 0) {
                $record->reward = bcmul($newAmount, bcdiv((string) $bonus->rate, '100', 8), 4);
            }
            $record->save();
        }

        (new ActiveUserService())->onUserRecharged($userId);
    }

    private function isActivityOpen(ActivityModel $activity): bool
    {
        if ((int) $activity->status !== 1) {
            return false;
        }
        $now = time();
        if ($activity->started_at && (int) $activity->started_at > $now) {
            return false;
        }
        if ($activity->ended_at && (int) $activity->ended_at < $now) {
            return false;
        }

        return true;
    }
}
