<?php

declare(strict_types=1);

namespace app\service\api;

use app\exception\BusinessException;
use app\model\activity\ActivityInviteTierModel;
use app\model\activity\ActivityModel;
use app\model\activity\ActivityPasswordModel;
use app\model\activity\ActivityPasswordRecordModel;
use app\model\activity\ActivityRechargeBonusModel;
use app\model\activity\ActivityRecordModel;
use app\model\member\UserModel;
use app\service\MemberWalletService;
use app\service\RechargeBonusCommissionService;
use app\service\SettingService;
use app\support\ErrorCode;
use app\support\UserWaterDetailType;
use Illuminate\Database\Capsule\Manager as DB;

class ApiActivityService
{
    public function __construct(
        private ?MemberWalletService $walletService = null,
        private ?RechargeBonusCommissionService $commissionService = null,
        private ?SettingService $settingService = null,
    ) {
        $this->walletService = $walletService ?? new MemberWalletService();
        $this->commissionService = $commissionService ?? new RechargeBonusCommissionService();
        $this->settingService = $settingService ?? new SettingService();
    }

    public function list(int $userId, int|array|null $type = null): array
    {
        $query = ActivityModel::query()->orderByDesc('id');
        if ($type !== null) {
            if (is_array($type)) {
                $types = array_values(array_filter(array_map('intval', $type)));
                $query->whereIn('type', $types ?: [7, 8, 9]);
            } else {
                $query->where('type', $type);
            }
        } else {
            $query->whereIn('type', [7, 8, 9]);
        }

        $records = ActivityRecordModel::where('user_id', $userId)->get()->groupBy('activity_id');

        return $query->get()->map(function ($act) use ($userId, $records) {
            $userRecords = $records->get($act->id) ?? collect();
            $data = array_merge($act->toArray(), [
                'type_text' => ActivityModel::$typeMap[$act->type] ?? '',
                'is_open' => $this->isActivityOpen($act),
            ]);

            return match ((int) $act->type) {
                ActivityModel::TYPE_INVITE_VALID => $this->formatInviteActivity($data, $userRecords),
                ActivityModel::TYPE_RECHARGE_BONUS, ActivityModel::TYPE_TRIAL_VOUCHER => $this->formatPasswordActivity($data, $userId, (int) $act->id),
                default => array_merge($data, [
                    'joined' => $userRecords->isNotEmpty(),
                    'can_receive' => $userRecords->where('status', 0)->where('reward', '>', 0)->count(),
                ]),
            };
        })->values()->all();
    }

    public function join(int $userId, int $activityId, string $password = ''): array
    {
        $activity = ActivityModel::find($activityId);
        if (!$activity) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '活动不存在');
        }

        if (!in_array((int) $activity->type, [ActivityModel::TYPE_RECHARGE_BONUS, ActivityModel::TYPE_TRIAL_VOUCHER], true)) {
            if ((int) $activity->status !== 1) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '活动已停止');
            }
            if ($activity->password !== '' && $activity->password !== $password) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '活动密码错误');
            }
            $record = ActivityRecordModel::firstOrCreate(
                ['user_id' => $userId, 'activity_id' => $activityId],
                ['password' => $password, 'status' => 0]
            );
            return $record->toArray();
        }

        if ($password === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请输入活动解锁编码');
        }

        $batch = ActivityPasswordModel::where('activity_id', $activityId)
            ->where('password', $password)
            ->where('status', 1)
            ->first();
        if (!$batch) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '活动解锁密码不正确');
        }

        if ((int) $batch->num > 0) {
            $usedCount = ActivityPasswordRecordModel::where('activity_id', $activityId)
                ->where('password', $password)
                ->count();
            if ($usedCount >= (int) $batch->num) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '活动编码已用完');
            }
        }

        $record = ActivityPasswordRecordModel::firstOrCreate(
            ['user_id' => $userId, 'activity_id' => $activityId],
            ['password' => $password, 'is_virtual' => (int) (UserModel::find($userId)?->is_virtual ?? 0)]
        );
        if ($record->password !== $password) {
            $record->password = $password;
            $record->save();
        }

        return ['joined' => true, 'activity_id' => $activityId];
    }

    public function getReward(int $userId, int $activityId, int $rechargeBonusId = 0): array
    {
        $activity = ActivityModel::find($activityId);
        if (!$activity) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '活动不存在');
        }

        if ((int) $activity->type === ActivityModel::TYPE_TRIAL_VOUCHER) {
            return $this->claimTrialVoucher($userId, $activity);
        }

        if ((int) $activity->type === ActivityModel::TYPE_INVITE_VALID) {
            return $this->claimInviteReward($userId, $activity, $rechargeBonusId);
        }

        return $this->claimRechargeBonusReward($userId, $activity, $rechargeBonusId);
    }

    public function rechargeBonusList(int $userId, int $activityId): array
    {
        $activity = ActivityModel::find($activityId);
        if (!$activity || (int) $activity->type !== ActivityModel::TYPE_RECHARGE_BONUS) {
            return [];
        }

        $bonuses = ActivityRechargeBonusModel::where('activity_id', $activityId)
            ->where('status', 1)->orderBy('amount')->get();
        $records = ActivityRecordModel::where('user_id', $userId)->where('activity_id', $activityId)->get();

        return $bonuses->map(function ($bonus) use ($records, $activity) {
            $record = $records->firstWhere('recharge_bonus_id', $bonus->id);
            return [
                'id' => $bonus->id,
                'amount' => (string) $bonus->amount,
                'rate' => (string) $bonus->rate,
                'num' => $bonus->num,
                'end_time' => $activity->ended_at ? date('Y-m-d H:i:s', (int) $activity->ended_at) : '',
                'recharge_amount' => (string) ($record?->recharge_amount ?? 0),
                'reward' => (string) ($record?->reward ?? 0),
                'reward_value' => (float) ($record?->reward ?? 0),
                'joined' => $record !== null,
            ];
        })->all();
    }

    public function rechargeNow(int $userId, int $activityId, int $rechargeBonusId): array
    {
        $activity = ActivityModel::find($activityId);
        if (!$activity || (int) $activity->type !== ActivityModel::TYPE_RECHARGE_BONUS) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '活动不存在');
        }

        if (!$this->isActivityOpen($activity)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '活动暂未开放，请等待！');
        }

        if (!$this->hasValidPassword($userId, $activityId)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请重新输入活动解锁密码');
        }

        $bonus = ActivityRechargeBonusModel::where('id', $rechargeBonusId)
            ->where('activity_id', $activityId)->where('status', 1)->first();
        if (!$bonus) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '活动阶梯不存在');
        }

        $exists = ActivityRecordModel::where('activity_id', $activityId)
            ->where('recharge_bonus_id', $rechargeBonusId)
            ->where('user_id', $userId)
            ->where('status', 0)
            ->first();
        if ($exists) {
            return ['status' => 1];
        }

        $joinedCount = ActivityRecordModel::where('activity_id', $activityId)
            ->where('recharge_bonus_id', $rechargeBonusId)
            ->where('user_id', '<>', $userId)
            ->distinct('user_id')
            ->count('user_id');
        if ($joinedCount >= (int) $bonus->num) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '活动名额已满，无法参与');
        }

        $pwdRecord = ActivityPasswordRecordModel::where('user_id', $userId)
            ->where('activity_id', $activityId)->first();

        ActivityRecordModel::create([
            'activity_id' => $activityId,
            'recharge_bonus_id' => $rechargeBonusId,
            'user_id' => $userId,
            'from_user_id' => $userId,
            'status' => 0,
            'password' => $pwdRecord?->password ?? '',
        ]);

        return ['status' => 1];
    }

    public function claimRegisterTrial(int $userId): array
    {
        $user = UserModel::find($userId);
        if (!$user || (int) $user->kyc_status !== 1) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请先完成实名认证');
        }

        $activity = ActivityModel::where('type', ActivityModel::TYPE_TRIAL_VOUCHER)
            ->where('status', 1)->orderByDesc('id')->first();
        if (!$activity) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '暂无新人体验券活动');
        }

        return $this->claimTrialVoucher($userId, $activity, true);
    }

    private function claimTrialVoucher(int $userId, ActivityModel $activity, bool $isRegister = false): array
    {
        if (!$isRegister && !$this->hasValidPassword($userId, (int) $activity->id)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请先输入体验券编码参与活动');
        }

        return DB::transaction(function () use ($userId, $activity) {
            $money = (string) $activity->reward;
            $expiredDays = (float) $activity->expired_at;
            $returnAt = $expiredDays > 0 ? time() + (int) ($expiredDays * 86400) : 0;

            $this->walletService->changeBalance(
                $userId,
                $money,
                MemberWalletService::WALLET_TYJ,
                MemberWalletService::DIRECTION_IN,
                '体验券领取',
                UserWaterDetailType::TRIAL_BONUS,
                (int) $activity->id
            );

            $pwd = ActivityPasswordRecordModel::where('user_id', $userId)
                ->where('activity_id', $activity->id)->value('password') ?? '';

            ActivityRecordModel::create([
                'activity_id' => $activity->id,
                'user_id' => $userId,
                'from_user_id' => $userId,
                'reward' => $money,
                'reward_type' => 3,
                'status' => 1,
                'password' => $pwd,
                'returned_at' => $returnAt,
            ]);

            UserModel::where('id', $userId)->update([
                'trial_no_recharge' => 1,
                'withdraw_status' => 0,
            ]);

            return ['reward' => $money];
        });
    }

    private function claimInviteReward(int $userId, ActivityModel $activity, int $recordId): array
    {
        $extra = is_array($activity->extra) ? $activity->extra : [];
        if (!$this->isInClaimWindow($extra)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '当前时间无法领取');
        }

        return DB::transaction(function () use ($userId, $activity, $recordId) {
            $query = ActivityRecordModel::where('user_id', $userId)
                ->where('activity_id', $activity->id)
                ->where('status', 0)
                ->where('reward', '>', 0);
            if ($recordId > 0) {
                $query->where('id', $recordId);
            }
            $records = $query->lockForUpdate()->get();
            if ($records->isEmpty()) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无奖励可领取');
            }

            $totalReward = '0';
            $multiplier = 1.0;
            foreach ($records as $record) {
                $mult = (float) ($record->reward_multiplier ?: 1);
                $multiplier = max($multiplier, $mult);
                $userMult = (float) (UserModel::where('id', $userId)->value('reward_multiplier') ?: 1);
                if ($userMult > 0) {
                    $mult *= $userMult;
                }
                $money = bcmul((string) $record->reward, (string) $mult, 4);
                $this->walletService->changeBalance(
                    $userId, $money, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_IN,
                    '邀请有效用户奖励', UserWaterDetailType::INVITE_VALID_USER, (int) $record->id
                );
                $record->update(['status' => 1]);
                $totalReward = bcadd($totalReward, $money, 4);
            }

            return ['reward' => $totalReward, 'multiplier' => $multiplier];
        });
    }

    private function claimRechargeBonusReward(int $userId, ActivityModel $activity, int $rechargeBonusId): array
    {
        return DB::transaction(function () use ($userId, $activity, $rechargeBonusId) {
            $query = ActivityRecordModel::where('user_id', $userId)
                ->where('activity_id', $activity->id)
                ->where('status', 0)
                ->where('reward', '>', 0);
            if ($rechargeBonusId > 0) {
                $query->where('recharge_bonus_id', $rechargeBonusId);
            }
            $records = $query->lockForUpdate()->get();
            if ($records->isEmpty()) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '暂无奖励可领取');
            }

            foreach ($records as $record) {
                $money = (string) $record->reward;
                $this->walletService->changeBalance(
                    $userId, $money, MemberWalletService::WALLET_BASE, MemberWalletService::DIRECTION_IN,
                    '充值送金', UserWaterDetailType::RECHARGE_BONUS, (int) $record->id
                );
                $this->commissionService->distribute($userId, $money, (int) $activity->id, (int) $record->id);
                $record->update(['status' => 1]);

                ActivityRecordModel::create([
                    'activity_id' => $activity->id,
                    'recharge_bonus_id' => $record->recharge_bonus_id,
                    'user_id' => $userId,
                    'from_user_id' => $userId,
                    'status' => 0,
                    'password' => $record->password,
                ]);
            }

            return ['reward' => (string) $records->sum('reward')];
        });
    }

    private function formatInviteActivity(array $data, $userRecords): array
    {
        $pending = $userRecords->where('status', 0)->where('reward', '>', 0);
        return array_merge($data, [
            'records_count' => $pending->count(),
            'can_receive' => $pending->count(),
            'user_count' => $pending->count(),
            'ratio' => $pending->count() > 0 ? 100 : 0,
        ]);
    }

    private function formatPasswordActivity(array $data, int $userId, int $activityId): array
    {
        return array_merge($data, [
            'joined' => $this->hasValidPassword($userId, $activityId) ? 1 : 0,
            'is_reward' => ActivityRecordModel::where('activity_id', $activityId)
                ->where('user_id', $userId)->where('reward_type', 3)->exists() ? 1 : 0,
        ]);
    }

    public function isActivityOpen(ActivityModel $activity): bool
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

    private function hasValidPassword(int $userId, int $activityId): bool
    {
        return ActivityPasswordRecordModel::where('user_id', $userId)
            ->where('activity_id', $activityId)->exists();
    }

    private function isInClaimWindow(array $extra): bool
    {
        $start = (string) ($extra['claim_start'] ?? '00:00');
        $end = (string) ($extra['claim_end'] ?? '23:59');
        $now = date('H:i');
        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }

        return $now >= $start || $now <= $end;
    }
}
