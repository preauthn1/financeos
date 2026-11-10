<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\activity\ActivityInviteTierModel;
use app\model\activity\ActivityModel;
use app\model\activity\ActivityPasswordModel;
use app\model\activity\ActivityRechargeBonusModel;
use app\support\ErrorCode;

class ActivityConfigService
{
    private function ensureActivity(int $activityId, ?int $expectedType = null): ActivityModel
    {
        $activity = ActivityModel::find($activityId);
        if (!$activity) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '活动不存在');
        }
        if ($expectedType !== null && (int) $activity->type !== $expectedType) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '活动类型不匹配');
        }

        return $activity;
    }

    public function rechargeBonuses(int $activityId): array
    {
        $this->ensureActivity($activityId, ActivityModel::TYPE_RECHARGE_BONUS);

        return ActivityRechargeBonusModel::where('activity_id', $activityId)
            ->orderBy('amount')
            ->get()
            ->all();
    }

    public function createRechargeBonus(int $activityId, array $data): ActivityRechargeBonusModel
    {
        $this->ensureActivity($activityId, ActivityModel::TYPE_RECHARGE_BONUS);

        return ActivityRechargeBonusModel::create([
            'activity_id' => $activityId,
            'amount' => $data['amount'] ?? 0,
            'rate' => $data['rate'] ?? 0,
            'num' => (int) ($data['num'] ?? 1),
            'status' => (int) ($data['status'] ?? 1),
            'remark' => (string) ($data['remark'] ?? ''),
        ]);
    }

    public function updateRechargeBonus(int $activityId, int $id, array $data): ActivityRechargeBonusModel
    {
        $this->ensureActivity($activityId, ActivityModel::TYPE_RECHARGE_BONUS);
        $row = ActivityRechargeBonusModel::where('activity_id', $activityId)->where('id', $id)->first();
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '阶梯不存在');
        }
        $row->fill($data)->save();

        return $row->fresh();
    }

    public function deleteRechargeBonus(int $activityId, int $id): void
    {
        ActivityRechargeBonusModel::where('activity_id', $activityId)->where('id', $id)->delete();
    }

    public function passwords(int $activityId): array
    {
        $activity = $this->ensureActivity($activityId);
        if (!in_array((int) $activity->type, [ActivityModel::TYPE_RECHARGE_BONUS, ActivityModel::TYPE_TRIAL_VOUCHER], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '该活动类型不支持解锁编码');
        }

        return ActivityPasswordModel::where('activity_id', $activityId)
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function createPassword(int $activityId, array $data): ActivityPasswordModel
    {
        $activity = $this->ensureActivity($activityId);
        if (!in_array((int) $activity->type, [ActivityModel::TYPE_RECHARGE_BONUS, ActivityModel::TYPE_TRIAL_VOUCHER], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '该活动类型不支持解锁编码');
        }

        return ActivityPasswordModel::create([
            'activity_id' => $activityId,
            'password' => (string) ($data['password'] ?? ''),
            'password_updated_at' => time(),
            'num' => (int) ($data['num'] ?? 1),
            'status' => (int) ($data['status'] ?? 1),
            'remark' => (string) ($data['remark'] ?? ''),
        ]);
    }

    public function deletePassword(int $activityId, int $id): void
    {
        ActivityPasswordModel::where('activity_id', $activityId)->where('id', $id)->delete();
    }

    public function inviteTiers(int $activityId): array
    {
        $this->ensureActivity($activityId, ActivityModel::TYPE_INVITE_VALID);

        return ActivityInviteTierModel::where('activity_id', $activityId)
            ->orderBy('sort')
            ->orderBy('invite_num')
            ->get()
            ->all();
    }

    public function createInviteTier(int $activityId, array $data): ActivityInviteTierModel
    {
        $this->ensureActivity($activityId, ActivityModel::TYPE_INVITE_VALID);

        return ActivityInviteTierModel::create([
            'activity_id' => $activityId,
            'invite_num' => (int) ($data['invite_num'] ?? 0),
            'multiplier' => $data['multiplier'] ?? 1,
            'winner_rate' => $data['winner_rate'] ?? 0,
            'status' => (int) ($data['status'] ?? 1),
            'sort' => (int) ($data['sort'] ?? 0),
        ]);
    }

    public function updateInviteTier(int $activityId, int $id, array $data): ActivityInviteTierModel
    {
        $this->ensureActivity($activityId, ActivityModel::TYPE_INVITE_VALID);
        $row = ActivityInviteTierModel::where('activity_id', $activityId)->where('id', $id)->first();
        if (!$row) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '阶梯不存在');
        }
        $row->fill($data)->save();

        return $row->fresh();
    }

    public function deleteInviteTier(int $activityId, int $id): void
    {
        ActivityInviteTierModel::where('activity_id', $activityId)->where('id', $id)->delete();
    }
}
