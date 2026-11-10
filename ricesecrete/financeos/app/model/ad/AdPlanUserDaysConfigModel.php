<?php

declare(strict_types=1);

namespace app\model\ad;

use app\model\Concerns\DefinesTableSchema;
use app\model\member\UserModel;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;

class AdPlanUserDaysConfigModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'ad_plan_user_days_config';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'ad_plan_id', 'day', 'task_session_id', 'level_id',
        'invest_rate', 'invest_profit', 'invest_day', 'invest_period_type',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'ad_plan_user_days_config',
            'comment' => '用户广告计划天数配置',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'ad_plan_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '广告计划ID'],
                'day' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '天数'],
                'task_session_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '时段ID'],
                'level_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '等级ID'],
                'invest_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '占余额比例'],
                'invest_profit' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '收益率'],
                'invest_day' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '周期'],
                'invest_period_type' => ['type' => 'string', 'length' => 10, 'default' => 'day', 'comment' => '周期类型'],
            ],
            'indexes' => [
                ['columns' => ['user_id', 'ad_plan_id', 'day', 'task_session_id', 'level_id'], 'name' => 'idx_ad_plan_user_day'],
            ],
        ];
    }

    /** @return array<string, array<string, array<string, array<string, mixed>>>> */
    public static function getUserConfig(int $userId, int $adPlanId): array
    {
        $configs = self::where('user_id', $userId)
            ->where('ad_plan_id', $adPlanId)
            ->get();

        if ($configs->isEmpty()) {
            return [];
        }

        $daysConfig = [];
        foreach ($configs as $config) {
            $daysConfig[(int) $config->day][(int) $config->task_session_id][(int) $config->level_id] = [
                'invest_rate' => (float) $config->invest_rate,
                'invest_profit' => (float) $config->invest_profit,
                'invest_period_type' => (string) $config->invest_period_type,
                'invest_day' => (float) $config->invest_day,
            ];
        }

        return $daysConfig;
    }

    /** @return array<string, mixed>|null */
    public static function getDayLevelConfig(int $userId, int $adPlanId, int $day, int $taskSessionId, int $levelId): ?array
    {
        $config = self::getUserConfig($userId, $adPlanId);
        return $config[$day][$taskSessionId][$levelId] ?? null;
    }

    /**
     * @param array<int|string, array<int|string, array<int|string, array<string, mixed>>>> $daysConfig
     */
    public static function saveUserConfig(int $userId, int $adPlanId, array $daysConfig, string $applyScope = 'personal'): void
    {
        $userIds = [$userId];
        if ($applyScope === 'team') {
            $teamIds = UserModel::query()
                ->where('tree_id', 'like', "%/{$userId}/%")
                ->where('tree_id', 'not like', "%/{$userId}/")
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
            $userIds = array_values(array_unique(array_merge($userIds, $teamIds)));
        }

        DB::transaction(function () use ($userIds, $adPlanId, $daysConfig) {
            foreach ($userIds as $uid) {
                self::where('user_id', $uid)->where('ad_plan_id', $adPlanId)->delete();

                $rows = [];
                foreach ($daysConfig as $day => $timeSlots) {
                    if (!is_array($timeSlots)) {
                        continue;
                    }
                    foreach ($timeSlots as $sessionId => $levels) {
                        if (!is_array($levels)) {
                            continue;
                        }
                        foreach ($levels as $levelId => $config) {
                            if (!is_array($config)) {
                                continue;
                            }
                            $investRate = (float) ($config['invest_rate'] ?? 0);
                            $investProfit = (float) ($config['invest_profit'] ?? 0);
                            if ($investRate <= 0 && $investProfit <= 0) {
                                continue;
                            }
                            $rows[] = [
                                'user_id' => (int) $uid,
                                'ad_plan_id' => $adPlanId,
                                'day' => (int) $day,
                                'task_session_id' => (int) $sessionId,
                                'level_id' => (int) $levelId,
                                'invest_rate' => $investRate,
                                'invest_profit' => $investProfit,
                                'invest_period_type' => (string) ($config['invest_period_type'] ?? 'day'),
                                'invest_day' => (float) ($config['invest_day'] ?? 0),
                            ];
                        }
                    }
                }

                if ($rows !== []) {
                    self::insert($rows);
                }
            }
        });
    }
}
