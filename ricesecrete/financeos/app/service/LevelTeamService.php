<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\ad\AdConfigModel;
use app\model\member\LevelTeamModel;
use app\model\member\UserModel;
use app\service\api\FinanceProductLevelTeamService;
use app\support\ErrorCode;

class LevelTeamService
{
    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = LevelTeamModel::query()->with('user:id,username,nickname');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['level_id'])) {
            $query->where('level_id', (int) $filters['level_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(function ($row) {
                $data = $row->toArray();
                $data['username'] = $row->user?->username ?? '';
                unset($data['user']);
                return $data;
            });

        return ['total' => $total, 'items' => $items];
    }

    /** @return array{total:int,items:array<int,array<string,mixed>>,summary:array<string,mixed>} */
    public function listForUser(int $userId): array
    {
        if ($userId <= 0 || !UserModel::where('id', $userId)->exists()) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $levels = AdConfigModel::query()
            ->where('is_show', 1)
            ->whereIn('type', [AdConfigModel::TYPE_NORMAL, AdConfigModel::TYPE_PROMOTION])
            ->orderBy('sort')
            ->orderBy('level_id')
            ->get(['id', 'level_id', 'level_name', 'type', 'invest_num', 'invest_rate', 'invest_max_rate', 'active_rate', 'invest_profit', 'invest_day', 'invest_period_type']);

        $teamMap = LevelTeamModel::where('user_id', $userId)
            ->get()
            ->keyBy(fn (LevelTeamModel $row) => (int) $row->level_id);

        $enabled = 0;
        $items = $levels->map(function (AdConfigModel $level) use ($userId, $teamMap, &$enabled) {
            $team = $teamMap->get((int) $level->level_id);
            $status = $team ? (int) $team->status : 0;
            if ($status === LevelTeamModel::STATUS_ENABLE) {
                $enabled++;
            }

            return [
                'id' => $team ? (int) $team->id : null,
                'user_id' => $userId,
                'level_id' => (int) $level->level_id,
                'level_name' => (string) $level->level_name,
                'type' => (int) $level->type,
                'invest_num' => $team ? (int) $team->invest_num : (int) $level->invest_num,
                'invest_rate' => $team ? (float) $team->invest_rate : (float) $level->invest_rate,
                'invest_max_rate' => $team ? (float) $team->invest_max_rate : (float) $level->invest_max_rate,
                'active_rate' => $team ? (float) $team->active_rate : (float) $level->active_rate,
                'invest_profit' => $team ? (float) $team->invest_profit : (float) $level->invest_profit,
                'invest_day' => $team ? (float) $team->invest_day : (float) $level->invest_day,
                'invest_period_type' => $team
                    ? (string) $team->invest_period_type
                    : (string) ($level->invest_period_type ?: 'day'),
                'invest_password' => $team ? (string) $team->invest_password : '',
                'status' => $status,
                'subordinate' => $team ? (int) $team->subordinate : 0,
                'created_at' => $team?->created_at,
                'updated_at' => $team?->updated_at,
                'configured' => $team !== null,
            ];
        })->values()->all();

        return [
            'total' => count($items),
            'items' => $items,
            'summary' => [
                'enabled' => $enabled > 0,
                'enabled_count' => $enabled,
                'total' => count($items),
                'configured_count' => $teamMap->count(),
            ],
        ];
    }

    public function show(int $id): LevelTeamModel
    {
        $item = LevelTeamModel::with('user')->find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '配置不存在');
        }
        return $item;
    }

    public function create(array $data): LevelTeamModel
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $levelId = (int) ($data['level_id'] ?? 0);
        if (!UserModel::where('id', $userId)->exists()) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }
        if (LevelTeamModel::where('user_id', $userId)->where('level_id', $levelId)->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '该用户等级团队配置已存在');
        }

        $item = LevelTeamModel::create(array_merge($data, [
            'status' => (int) ($data['status'] ?? LevelTeamModel::STATUS_ENABLE),
        ]));
        (new FinanceProductLevelTeamService())->clearCache($userId, $levelId);
        return $item;
    }

    public function update(int $id, array $data): LevelTeamModel
    {
        $item = $this->show($id);
        $item->fill($data)->save();
        (new FinanceProductLevelTeamService())->clearCache((int) $item->user_id, (int) $item->level_id);
        if ((int) $item->subordinate === 1 && (int) $item->status === LevelTeamModel::STATUS_ENABLE) {
            $this->syncToSubordinates($item);
        }
        return $item->fresh(['user']);
    }

    public function toggle(int $id, string $field, int $value): LevelTeamModel
    {
        if (!in_array($field, ['status', 'subordinate'], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '参数错误');
        }

        $item = $this->show($id);
        $item->{$field} = $value ? 1 : 0;
        $item->save();
        (new FinanceProductLevelTeamService())->clearCache((int) $item->user_id, (int) $item->level_id);

        if ((int) $item->subordinate === 1 && (int) $item->status === LevelTeamModel::STATUS_ENABLE) {
            $this->syncToSubordinates($item);
        }

        return $item->fresh(['user']);
    }

    public function delete(int $id): void
    {
        $item = LevelTeamModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '配置不存在');
        }
        $userId = (int) $item->user_id;
        $levelId = (int) $item->level_id;
        $item->delete();
        (new FinanceProductLevelTeamService())->clearCache($userId, $levelId);
    }

    private function syncToSubordinates(LevelTeamModel $levelTeam): void
    {
        $userId = (int) $levelTeam->user_id;
        $levelId = (int) $levelTeam->level_id;
        $teamIds = UserModel::query()
            ->where('tree_id', 'like', "%/{$userId}/%")
            ->where('tree_id', 'not like', "%/{$userId}/")
            ->pluck('id')
            ->all();

        $payload = [
            'invest_rate' => $levelTeam->invest_rate,
            'active_rate' => $levelTeam->active_rate,
            'invest_max_rate' => $levelTeam->invest_max_rate,
            'invest_num' => $levelTeam->invest_num,
            'invest_profit' => $levelTeam->invest_profit,
            'invest_day' => $levelTeam->invest_day,
            'invest_period_type' => $levelTeam->invest_period_type,
            'status' => $levelTeam->status,
        ];

        foreach ($teamIds as $subUserId) {
            $subUserId = (int) $subUserId;
            $sub = LevelTeamModel::where('user_id', $subUserId)->where('level_id', $levelId)->first();
            if ($sub) {
                $sub->fill($payload)->save();
            } else {
                LevelTeamModel::create(array_merge($payload, [
                    'user_id' => $subUserId,
                    'level_id' => $levelId,
                    'subordinate' => 0,
                ]));
            }
            (new FinanceProductLevelTeamService())->clearCache($subUserId, $levelId);
        }
    }
}
