<?php

declare(strict_types=1);

namespace app\model\ad;

use app\model\Concerns\DefinesTableSchema;
use app\model\member\UserModel;
use app\support\DateTimeFormat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AdPlanModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'ad_plan';

    protected $fillable = ['name', 'period', 'next_id', 'sort', 'status'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'ad_plan',
            'comment' => '广告位购买计划',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'name' => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '计划名称'],
                'period' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '计划周期(天)'],
                'next_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '下一计划ID'],
                'sort' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '排序'],
                'status' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '状态'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
        ];
    }

    public static function getPlanName(int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        return (string) (self::where('id', $id)->value('name') ?? '');
    }

    public static function getDayCount(int $userId, int $planId, bool $isReal = false): int
    {
        if ($planId <= 0) {
            return 0;
        }

        $record = AdPlanUserModel::where('user_id', $userId)
            ->where('ad_plan_id', $planId)
            ->orderByDesc('id')
            ->first();
        if (!$record || !$record->created_at) {
            return 0;
        }

        $elapsed = (int) floor((time() - DateTimeFormat::unix($record->created_at)) / 86400);
        $day = $elapsed + 1;
        if ($isReal) {
            return $day;
        }

        $period = (int) (self::where('id', $planId)->value('period') ?? 0);

        return $period > 0 ? min($day, $period) : $day;
    }

    /** @return int|false */
    public static function updateUserPlanLevel(int $userId)
    {
        $userAdPlan = AdPlanUserModel::where('user_id', $userId)->orderByDesc('id')->first();
        if (!$userAdPlan) {
            $user = UserModel::find($userId);
            if (!$user) {
                return false;
            }

            $defaultPlan = self::where('status', 1)->orderBy('sort')->orderBy('id')->first();
            if (!$defaultPlan) {
                return false;
            }

            AdPlanUserModel::create([
                'user_id' => $userId,
                'ad_plan_id' => $defaultPlan->id,
                'previous_plan_id' => 0,
            ]);
            UserModel::where('id', $userId)->update(['ad_plan_id' => $defaultPlan->id]);

            return (int) $defaultPlan->id;
        }

        $currentPlanId = (int) $userAdPlan->ad_plan_id;
        if ($currentPlanId <= 0) {
            return 0;
        }

        $currentPlan = self::find($currentPlanId);
        if (!$currentPlan) {
            return false;
        }

        $totalDays = (int) ceil((time() - DateTimeFormat::unix($userAdPlan->created_at)) / 86400);

        if ((int) $currentPlan->status === 0) {
            $nextPlan = self::where('status', 1)->find((int) $currentPlan->next_id);
            if ($nextPlan) {
                $startAt = DateTimeFormat::unix($currentPlan->updated_at) + 86400;

                return self::createPlanRecord($userId, (int) $nextPlan->id, $currentPlanId, $startAt);
            }
        }

        if ($totalDays <= (int) $currentPlan->period) {
            return $currentPlanId;
        }

        $remainingDays = $totalDays;
        $planToCheck = $currentPlan;

        while ($remainingDays > (int) $planToCheck->period) {
            $remainingDays -= (int) $planToCheck->period;

            if (!(int) $planToCheck->next_id) {
                self::createPlanRecord(
                    $userId,
                    0,
                    (int) $planToCheck->id,
                    $remainingDays > 0 ? time() - ($remainingDays - 1) * 86400 : time()
                );

                return 0;
            }

            $nextPlan = self::find((int) $planToCheck->next_id);
            if (!$nextPlan) {
                break;
            }

            $planToCheck = $nextPlan;
        }

        if ((int) $planToCheck->id !== $currentPlanId) {
            self::createPlanRecord(
                $userId,
                (int) $planToCheck->id,
                $currentPlanId,
                $remainingDays > 0 ? time() - ($remainingDays - 1) * 86400 : time()
            );
        }

        return (int) $planToCheck->id;
    }

    private static function createPlanRecord(int $userId, int $planId, int $previousPlanId, int $createdAtUnix): int
    {
        AdPlanUserModel::create([
            'user_id' => $userId,
            'ad_plan_id' => $planId,
            'previous_plan_id' => $previousPlanId,
            'created_at' => Carbon::createFromTimestamp($createdAtUnix),
        ]);
        UserModel::where('id', $userId)->update(['ad_plan_id' => $planId]);

        return $planId;
    }
}
