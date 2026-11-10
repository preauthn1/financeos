<?php

declare(strict_types=1);

namespace app\service\api;

use app\model\member\LevelTeamInvestTimeModel;
use app\model\member\LevelTeamModel;
use support\Redis;

class FinanceProductLevelTeamService
{
    private const CACHE_PREFIX = 'team_level_config:';

    /** @return array{0: array<string,mixed>, 1: bool} */
    public function applyTeamLevel(int $userId, int $levelId, array $baseLevel): array
    {
        $team = $this->getConfig($userId, $levelId);
        if (!$team) {
            return [$baseLevel, false];
        }

        $merged = array_merge($baseLevel, [
            'invest_num' => (int) ($team['invest_num'] ?? $baseLevel['invest_num'] ?? 1),
            'invest_rate' => (float) ($team['invest_rate'] ?? $baseLevel['invest_rate'] ?? 0),
            'invest_profit' => (float) ($team['invest_profit'] ?? $baseLevel['invest_profit'] ?? 0),
            'invest_day' => (float) ($team['invest_day'] ?? $baseLevel['invest_day'] ?? 1),
            'invest_period_type' => (string) ($team['invest_period_type'] ?? $baseLevel['invest_period_type'] ?? 'day'),
            'active_rate' => (float) ($team['active_rate'] ?? $baseLevel['active_rate'] ?? 0),
            'invest_max_rate' => (float) ($team['invest_max_rate'] ?? $baseLevel['invest_max_rate'] ?? 0),
            'invest_password' => (string) ($team['invest_password'] ?? $baseLevel['invest_password'] ?? ''),
        ]);

        $timeConfig = LevelTeamInvestTimeModel::getCurrentConfig($userId, $levelId);
        if ($timeConfig) {
            if ((float) $timeConfig->invest_rate > 0) {
                $merged['invest_rate'] = (float) $timeConfig->invest_rate;
            }
            if ((float) $timeConfig->profit_rate > 0) {
                $merged['invest_profit'] = (float) $timeConfig->profit_rate;
            }
        }

        return [$merged, true];
    }

    public function disableAfterMatch(int $userId, int $levelId): void
    {
        $model = LevelTeamModel::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->where('status', LevelTeamModel::STATUS_ENABLE)
            ->first();
        if ($model) {
            $model->update(['status' => LevelTeamModel::STATUS_DISABLE]);
            $this->clearCache($userId, $levelId);
        }
    }

    public function getConfig(int $userId, int $levelId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $levelId . ':' . $userId;
        $cached = Redis::get($cacheKey);
        if ($cached !== null && $cached !== false) {
            $decoded = json_decode((string) $cached, true);
            return is_array($decoded) && $decoded !== [] ? $decoded : null;
        }

        $model = LevelTeamModel::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->where('status', LevelTeamModel::STATUS_ENABLE)
            ->first();
        if (!$model) {
            Redis::setex($cacheKey, 30, json_encode([]));
            return null;
        }

        $data = $model->toArray();
        Redis::setex($cacheKey, 30, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $data;
    }

    public function clearCache(int $userId, int $levelId): void
    {
        Redis::del(self::CACHE_PREFIX . $levelId . ':' . $userId);
    }
}
