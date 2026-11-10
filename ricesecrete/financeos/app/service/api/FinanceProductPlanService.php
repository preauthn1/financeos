<?php

declare(strict_types=1);

namespace app\service\api;

use app\model\ad\AdPlanDaysConfigModel;
use app\model\ad\AdPlanModel;
use app\model\ad\AdPlanUserDaysConfigModel;
use app\model\member\UserModel;

class FinanceProductPlanService
{
    /** @return array{0: object|array|null, 1: int} */
    public function resolvePlan(UserModel $user, int $levelId, ?array $taskSession): array
    {
        $adPlanId = (int) $user->ad_plan_id;
        if ($adPlanId <= 0) {
            return [null, 0];
        }

        $newPlanId = AdPlanModel::updateUserPlanLevel((int) $user->id);
        if ($newPlanId !== false) {
            $adPlanId = (int) $newPlanId;
        }

        if ($adPlanId <= 0) {
            return [null, 0];
        }

        $day = AdPlanModel::getDayCount((int) $user->id, $adPlanId, true);
        if ($day <= 0) {
            return [null, $adPlanId];
        }

        $sessionId = (int) ($taskSession['id'] ?? 0);
        $plan = AdPlanDaysConfigModel::where('ad_plan_id', $adPlanId)
            ->where('task_session_id', $sessionId)
            ->where('level_id', $levelId)
            ->where('day', $day)
            ->first();

        if ($plan) {
            $userConfig = AdPlanUserDaysConfigModel::getDayLevelConfig(
                (int) $user->id,
                $adPlanId,
                $day,
                $sessionId,
                $levelId
            );
            if ($userConfig) {
                $plan->invest_profit = $userConfig['invest_profit'];
                $plan->invest_rate = $userConfig['invest_rate'];
                $plan->invest_period_type = $userConfig['invest_period_type'];
                $plan->invest_day = $userConfig['invest_day'];
            }
        }

        return [$plan, $adPlanId];
    }

    public function applyPlanToConfig(array $config, mixed $plan, int $adPlanId, bool $levelFirst): array
    {
        if ($levelFirst || !$plan) {
            return $config;
        }

        if ($adPlanId > 0 && $plan instanceof AdPlanDaysConfigModel) {
            return array_merge($config, [
                'invest_rate' => (float) $plan->invest_rate,
                'invest_profit' => (float) $plan->invest_profit,
                'cycle' => (float) $plan->invest_day,
                'invest_period_type' => (string) $plan->invest_period_type,
                'ad_plan_days_config_id' => (int) $plan->id,
                'is_plan' => 1,
            ]);
        }

        return $config;
    }
}
