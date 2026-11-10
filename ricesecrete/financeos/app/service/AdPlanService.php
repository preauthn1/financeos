<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\ad\AdConfigModel;
use app\model\ad\AdPlanDaysConfigModel;
use app\model\ad\AdPlanModel;
use app\model\ad\AdPlanUserDaysConfigModel;
use app\model\member\UserModel;
use app\support\DateTimeFormat;
use app\support\ErrorCode;
use Illuminate\Database\Capsule\Manager as DB;

class AdPlanService
{
    public function listPlans(): array
    {
        $plans = AdPlanModel::orderBy('sort')->orderBy('id')->get();
        $nameMap = $plans->pluck('name', 'id')->all();

        return $plans->map(static function (AdPlanModel $plan) use ($nameMap) {
            $data = $plan->toArray();
            $nextId = (int) $plan->next_id;
            $data['next_plan'] = $nextId > 0 ? (string) ($nameMap[$nextId] ?? '-') : '-';
            $data['status'] = (int) $plan->status;
            return $data;
        })->values()->all();
    }

    public function meta(?int $excludePlanId = null): array
    {
        $plansQuery = AdPlanModel::orderBy('sort')->orderBy('id');
        if ($excludePlanId) {
            $plansQuery->where('id', '!=', $excludePlanId);
        }

        $levels = AdConfigModel::query()
            ->where('is_show', 1)
            ->whereIn('type', [AdConfigModel::TYPE_NORMAL, AdConfigModel::TYPE_PROMOTION])
            ->orderBy('sort')
            ->orderBy('level_id')
            ->get(['level_id', 'level_name', 'invest_rate', 'invest_profit', 'invest_day', 'invest_period_type'])
            ->map(static fn (AdConfigModel $row) => [
                'level_id' => (int) $row->level_id,
                'level_name' => (string) $row->level_name,
                'invest_rate' => (float) $row->invest_rate,
                'invest_profit' => (float) $row->invest_profit,
                'invest_day' => (float) $row->invest_day,
                'invest_period_type' => (string) ($row->invest_period_type ?: 'day'),
            ])
            ->values()
            ->all();

        $sessions = (new AdConfigService())->getTaskSessions();
        if ($sessions === []) {
            $sessions = [[
                'id' => 1,
                'started_at' => '00:00:00',
                'ended_at' => '23:59:59',
                'status' => 1,
            ]];
        }

        return [
            'plans' => $plansQuery->get(['id', 'name', 'period', 'next_id', 'sort', 'status'])->toArray(),
            'levels' => $levels,
            'sessions' => array_values(array_map(static function (array $session): array {
                return [
                    'id' => (int) ($session['id'] ?? 0),
                    'started_at' => (string) ($session['started_at'] ?? $session['start_time'] ?? '00:00:00'),
                    'ended_at' => (string) ($session['ended_at'] ?? $session['end_time'] ?? '23:59:59'),
                    'status' => (int) ($session['status'] ?? 1),
                ];
            }, $sessions)),
        ];
    }

    public function showPlan(int $id): array
    {
        $plan = AdPlanModel::find($id);
        if (!$plan) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '计划不存在');
        }

        return $this->formatPlanDetail($plan);
    }

    public function showUserPlan(int $userId): array
    {
        $user = UserModel::find($userId);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $planId = (int) $user->ad_plan_id;
        if ($planId <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '该用户未参与广告计划');
        }

        $plan = AdPlanModel::find($planId);
        if (!$plan) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '计划不存在');
        }

        $detail = $this->formatPlanDetail($plan);
        $userConfig = AdPlanUserDaysConfigModel::getUserConfig($userId, $planId);
        if ($userConfig !== []) {
            $detail['days_config'] = $userConfig;
            $detail['day_count'] = max(array_map('intval', array_keys($userConfig)));
            $detail['has_user_config'] = true;
        } else {
            $detail['has_user_config'] = false;
        }

        $detail['user'] = [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'ad_plan_id' => $planId,
        ];

        return $detail;
    }

    public function savePlan(array $data, ?int $id = null): array
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'period' => max(1, (int) ($data['period'] ?? 1)),
            'next_id' => (int) ($data['next_id'] ?? 0),
            'sort' => (int) ($data['sort'] ?? 0),
            'status' => (int) ($data['status'] ?? 0),
        ];
        if ($payload['name'] === '') {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请填写计划名称');
        }

        $daysConfig = $data['days_config'] ?? null;

        return DB::transaction(function () use ($payload, $daysConfig, $id) {
            if ($id) {
                $plan = AdPlanModel::find($id);
                if (!$plan) {
                    throw new BusinessException(ErrorCode::NOT_FOUND, '计划不存在');
                }
                // 编辑时周期由天数配置推导，不直接改 period 输入（兼容前端传 period）
                if (is_array($daysConfig)) {
                    $payload['period'] = max(1, count($daysConfig));
                }
                $plan->fill($payload)->save();
            } else {
                $plan = AdPlanModel::create($payload);
                $id = (int) $plan->id;
            }

            if (is_array($daysConfig)) {
                $this->replaceDayConfigs((int) $id, $daysConfig);
                $plan->period = max(1, count($daysConfig));
                $plan->save();
            }

            return $this->formatPlanDetail($plan->fresh());
        });
    }

    public function saveUserDaysConfig(int $userId, array $data): array
    {
        $user = UserModel::find($userId);
        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $planId = (int) ($data['ad_plan_id'] ?? $user->ad_plan_id);
        if ($planId <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '缺少广告计划');
        }
        if (!AdPlanModel::where('id', $planId)->exists()) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '计划不存在');
        }

        $daysConfig = $data['days_config'] ?? [];
        if (!is_array($daysConfig) || $daysConfig === []) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请填写天数配置');
        }

        $applyScope = (string) ($data['apply_scope'] ?? 'personal');
        if (!in_array($applyScope, ['personal', 'team'], true)) {
            $applyScope = 'personal';
        }

        AdPlanUserDaysConfigModel::saveUserConfig($userId, $planId, $daysConfig, $applyScope);

        return $this->showUserPlan($userId);
    }

    public function deletePlan(int $id): void
    {
        $plan = AdPlanModel::find($id);
        if (!$plan) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '计划不存在');
        }
        if ((int) $plan->status === 1) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '启用中的计划不能删除，请先禁用');
        }

        DB::transaction(function () use ($plan, $id) {
            $plan->delete();
            AdPlanDaysConfigModel::where('ad_plan_id', $id)->delete();
        });
    }

    public function toggleStatus(int $id, int $status): array
    {
        $plan = AdPlanModel::find($id);
        if (!$plan) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '计划不存在');
        }
        $plan->status = $status ? 1 : 0;
        $plan->save();

        return $this->formatPlanDetail($plan->fresh());
    }

    /** @return array{total:int,items:array<int,array<string,mixed>>} */
    public function listUsers(int $page, int $limit, array $filters = []): array
    {
        $query = UserModel::query()->where('ad_plan_id', '>', 0);

        if (!empty($filters['id'])) {
            $query->where('id', (int) $filters['id']);
        }
        if (!empty($filters['username'])) {
            $query->where('username', 'like', '%' . trim((string) $filters['username']) . '%');
        }
        if (isset($filters['ad_plan_id']) && $filters['ad_plan_id'] !== '' && $filters['ad_plan_id'] !== null) {
            $query->where('ad_plan_id', (int) $filters['ad_plan_id']);
        }

        $planIdsForNext = null;
        if (isset($filters['next_id']) && $filters['next_id'] !== '' && $filters['next_id'] !== null) {
            $planIdsForNext = AdPlanModel::where('next_id', (int) $filters['next_id'])->pluck('id')->all();
            if ($planIdsForNext === []) {
                return ['total' => 0, 'items' => []];
            }
            $query->whereIn('ad_plan_id', $planIdsForNext);
        }

        $total = (clone $query)->count();
        $users = $query->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get(['id', 'username', 'ad_plan_id']);

        $planIds = $users->pluck('ad_plan_id')->filter()->unique()->values()->all();
        $plans = $planIds
            ? AdPlanModel::whereIn('id', $planIds)->get()->keyBy('id')
            : collect();
        $nextIds = $plans->pluck('next_id')->filter()->unique()->values()->all();
        $nextNames = $nextIds
            ? AdPlanModel::whereIn('id', $nextIds)->pluck('name', 'id')->all()
            : [];

        $joinMap = [];
        if ($planIds !== []) {
            $userIds = $users->pluck('id')->all();
            $joins = DB::table('ad_plan_user')
                ->whereIn('user_id', $userIds)
                ->whereIn('ad_plan_id', $planIds)
                ->orderByDesc('id')
                ->get(['user_id', 'ad_plan_id', 'created_at']);
            foreach ($joins as $join) {
                $key = ((int) $join->user_id) . ':' . ((int) $join->ad_plan_id);
                if (!isset($joinMap[$key])) {
                    $joinMap[$key] = $join->created_at;
                }
            }
        }

        $items = [];
        foreach ($users as $user) {
            $userId = (int) $user->id;
            $planId = (int) $user->ad_plan_id;
            $plan = $plans->get($planId);
            $day = AdPlanModel::getDayCount($userId, $planId);
            $nextId = $plan ? (int) $plan->next_id : 0;
            $items[] = [
                'id' => $userId,
                'username' => (string) $user->username,
                'ad_plan_id' => $planId,
                'ad_plan_name' => $plan ? (string) $plan->name : '-',
                'ad_plan_period' => $plan ? (int) $plan->period : 0,
                'day' => $day,
                'next_id' => $nextId,
                'next_plan_name' => $nextId > 0 ? (string) ($nextNames[$nextId] ?? '-') : '-',
                'join_at' => DateTimeFormat::datetime($joinMap[$userId . ':' . $planId] ?? null),
            ];
        }

        if (isset($filters['day']) && $filters['day'] !== '' && $filters['day'] !== null) {
            $dayFilter = (int) $filters['day'];
            $items = array_values(array_filter($items, static fn ($item) => (int) $item['day'] === $dayFilter));
            $total = count($items);
        }

        return ['total' => $total, 'items' => $items];
    }

    public function listDayConfigs(int $planId): array
    {
        return AdPlanDaysConfigModel::where('ad_plan_id', $planId)->orderBy('day')->get()->toArray();
    }

    public function saveDayConfig(array $data, ?int $id = null): AdPlanDaysConfigModel
    {
        if ($id) {
            $item = AdPlanDaysConfigModel::find($id);
            if (!$item) {
                throw new BusinessException(ErrorCode::NOT_FOUND, '配置不存在');
            }
            $item->fill($data)->save();
            return $item->fresh();
        }

        return AdPlanDaysConfigModel::create($data);
    }

    public function deleteDayConfig(int $id): void
    {
        $item = AdPlanDaysConfigModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '配置不存在');
        }
        $item->delete();
    }

    /** @return array<string, mixed> */
    private function formatPlanDetail(AdPlanModel $plan): array
    {
        $rows = AdPlanDaysConfigModel::where('ad_plan_id', $plan->id)->orderBy('day')->get();
        $daysConfig = [];
        foreach ($rows as $config) {
            $daysConfig[(int) $config->day][(int) $config->task_session_id][(int) $config->level_id] = [
                'invest_rate' => (float) $config->invest_rate,
                'invest_profit' => (float) $config->invest_profit,
                'invest_period_type' => (string) $config->invest_period_type,
                'invest_day' => (float) $config->invest_day,
            ];
        }

        $dayCount = $daysConfig !== [] ? max(array_map('intval', array_keys($daysConfig))) : (int) $plan->period;

        return [
            'id' => (int) $plan->id,
            'name' => (string) $plan->name,
            'period' => (int) $plan->period,
            'next_id' => (int) $plan->next_id,
            'sort' => (int) $plan->sort,
            'status' => (int) $plan->status,
            'day_count' => max(1, $dayCount),
            'days_config' => $daysConfig,
            'created_at' => DateTimeFormat::datetime($plan->created_at),
            'updated_at' => DateTimeFormat::datetime($plan->updated_at),
        ];
    }

    /** @param array<int|string, mixed> $daysConfig */
    private function replaceDayConfigs(int $planId, array $daysConfig): void
    {
        AdPlanDaysConfigModel::where('ad_plan_id', $planId)->delete();
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
                    $rows[] = [
                        'ad_plan_id' => $planId,
                        'day' => (int) $day,
                        'task_session_id' => (int) $sessionId,
                        'level_id' => (int) $levelId,
                        'invest_rate' => (float) ($config['invest_rate'] ?? 0),
                        'invest_profit' => (float) ($config['invest_profit'] ?? 0),
                        'invest_period_type' => (string) ($config['invest_period_type'] ?? 'day'),
                        'invest_day' => (float) ($config['invest_day'] ?? 0),
                    ];
                }
            }
        }
        if ($rows !== []) {
            AdPlanDaysConfigModel::insert($rows);
        }
    }
}
