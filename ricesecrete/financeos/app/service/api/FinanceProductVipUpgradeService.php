<?php

declare(strict_types=1);

namespace app\service\api;

use app\model\ad\AdProductOrderModel;
use app\model\member\TaskSummaryV2Model;
use app\model\member\UserLevelModel;
use app\model\member\UserModel;
use app\service\MemberWalletService;
use app\service\SettingService;
use Illuminate\Database\Capsule\Manager as DB;

class FinanceProductVipUpgradeService
{
    public function __construct(
        private ?FinanceProductLevelConfigService $levelService = null,
        private ?SettingService $settingService = null,
        private ?MemberWalletService $walletService = null,
    ) {
        $this->levelService = $levelService ?? new FinanceProductLevelConfigService();
        $this->settingService = $settingService ?? new SettingService();
        $this->walletService = $walletService ?? new MemberWalletService();
    }

    /** @return array{code:int,msg:string} */
    public function upgradeBeforeMatch(int $userId, int $levelId): array
    {
        $level = $this->levelService->getLevel($levelId);
        if (!$level) {
            return ['code' => 101, 'msg' => '广告库存不存在'];
        }

        $user = UserModel::find($userId);
        if (!$user) {
            return ['code' => 102, 'msg' => '用户不存在'];
        }

        $taskSession = $this->levelService->getCurrentTaskSession($levelId);
        if (!$taskSession) {
            return ['code' => 102, 'msg' => (string) ($level['unlock_time_disabled_tips'] ?? '目前无法购买')];
        }

        $pending = AdProductOrderModel::where('user_id', $userId)
            ->where('status', AdProductOrderModel::STATUS_PENDING)
            ->orderByDesc('id')->first();
        if ($pending && $pending->session_no && (int) $pending->level_id !== $levelId) {
            return ['code' => 12, 'msg' => '无法进入当前广告位'];
        }

        [$start, $end] = $this->sessionRange($taskSession);
        $currentType = UserLevelModel::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('id')
            ->value('type');
        $levelType = (int) ($level['type'] ?? FinanceProductLevelConfigService::TYPE_NORMAL);
        if ($currentType !== null) {
            if ((int) $currentType === $levelType) {
                return ['code' => 1, 'msg' => '升级成功'];
            }
            // 参考：时段内已有非体验等级时，其它非体验区互斥；体验区始终可进入
            if ((int) $currentType !== FinanceProductLevelConfigService::TYPE_EXPERIENCE
                && $levelType !== FinanceProductLevelConfigService::TYPE_EXPERIENCE) {
                return ['code' => 12, 'msg' => '无法进入当前广告位'];
            }
        }

        $inviteNum = (int) ($level['invite_num'] ?? 0);
        if ($inviteNum > 0) {
            $count = $this->countEffectiveInvites($userId, (float) ($level['invite_num_amount'] ?? 0));
            if ($count < $inviteNum) {
                return ['code' => 11, 'msg' => '邀请任务未完成'];
            }
        }

        if ($levelType === FinanceProductLevelConfigService::TYPE_PROMOTION) {
            $activeNum = (int) ($level['active_num'] ?? 0);
            if ($activeNum > 0) {
                $yesterday = date('Ymd', strtotime('-1 day'));
                $summary = TaskSummaryV2Model::where('user_id', $userId)->where('date', $yesterday)->first();
                $activeCount = (int) ($summary?->task_level1 ?? 0);
                if ($activeCount < $activeNum) {
                    return ['code' => 11, 'msg' => '昨日活跃人数未达标'];
                }
            }
        }

        if (bccomp((string) $user->balance, (string) ($level['price'] ?? 0), 4) < 0) {
            return ['code' => 12, 'msg' => '当前账户余额未满足该交易区最低竞价区间要求'];
        }

        $mode = (int) $this->settingService->getValue('level_upgrade_type', 1);
        return $mode === 2
            ? $this->upgradeWithDeduction($user, $level)
            : $this->upgradeFree($user, $level);
    }

    /** @return array{code:int,msg:string} */
    private function upgradeFree(UserModel $user, array $level): array
    {
        return $this->persistUpgrade($user, $level, false);
    }

    /** @return array{code:int,msg:string} */
    private function upgradeWithDeduction(UserModel $user, array $level): array
    {
        $price = (string) ($level['price'] ?? '0');
        try {
            return DB::transaction(function () use ($user, $level, $price) {
                $locked = UserModel::lockForUpdate()->find($user->id);
                if (!$locked) {
                    return ['code' => 102, 'msg' => '用户不存在'];
                }
                $oldLevel = $this->levelService->getLevel((int) $locked->level_id);
                $refund = (string) ($oldLevel['price'] ?? '0');

                $this->walletService->changeBalance(
                    (int) $locked->id,
                    $price,
                    MemberWalletService::WALLET_BASE,
                    MemberWalletService::DIRECTION_OUT,
                    '升级会员',
                    17
                );

                $result = $this->persistUpgrade($locked, $level, true);
                if ($result['code'] === 1 && bccomp($refund, '0', 4) > 0) {
                    $this->walletService->changeBalance(
                        (int) $locked->id,
                        $refund,
                        MemberWalletService::WALLET_BROKERAGE,
                        MemberWalletService::DIRECTION_IN,
                        '返还升级费用到佣金',
                        7
                    );
                }

                return $result;
            });
        } catch (\Throwable) {
            return ['code' => 102, 'msg' => '升级失败'];
        }
    }

    /** @return array{code:int,msg:string} */
    private function persistUpgrade(UserModel $user, array $level, bool $deducted): array
    {
        $levelId = (int) ($level['level_id'] ?? 0);
        $now = time();
        UserLevelModel::create([
            'user_id' => $user->id,
            'type' => (int) ($level['type'] ?? 0),
            'from_level' => (int) $user->level_id,
            'to_level' => $levelId,
            'amount' => (float) ($level['price'] ?? 0),
            'duration_days' => (int) ($level['level_at'] ?? 365),
            'duration_type' => 1,
            'expired_at' => $now + ((int) ($level['level_at'] ?? 365)) * 86400,
        ]);

        if ((int) ($level['type'] ?? 0) !== FinanceProductLevelConfigService::TYPE_EXPERIENCE
            && (int) $user->level_id !== $levelId) {
            $user->update(['level_id' => $levelId]);
        }

        return ['code' => 1, 'msg' => '升级成功'];
    }

    private function countEffectiveInvites(int $userId, float $minRecharge): int
    {
        $query = UserModel::where('leader1', $userId);
        if ($minRecharge > 0) {
            $query->where('total_recharge', '>=', $minRecharge);
        }
        return $query->count();
    }

    /** @return array{0:int,1:int} */
    private function sessionRange(array $taskSession): array
    {
        $date = date('Y-m-d');
        return [
            strtotime($date . ' ' . ($taskSession['started_at'] ?? '00:00:00')),
            strtotime($date . ' ' . ($taskSession['ended_at'] ?? '23:59:59')),
        ];
    }
}
