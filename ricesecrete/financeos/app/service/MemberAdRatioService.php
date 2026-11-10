<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\ad\AdConfigModel;
use app\model\member\UserAdRatioConfigModel;
use app\model\member\UserModel;
use app\support\ErrorCode;

class MemberAdRatioService
{
    /** @var array<int, string> */
    private const AREA_KEYS = [
        AdConfigModel::TYPE_EXPERIENCE => 'exp',
        AdConfigModel::TYPE_NORMAL => 'personal',
        AdConfigModel::TYPE_PROMOTION => 'agent',
        AdConfigModel::TYPE_ACTIVITY => 'activity',
    ];

    public function getBundle(int $userId): array
    {
        if (!UserModel::find($userId)) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $areas = $this->resolveAreas();
        $configs = UserAdRatioConfigModel::where('user_id', $userId)->get()->keyBy('level_id');
        $configMap = [];

        foreach ($areas as $area) {
            $levelId = (int) $area['level_id'];
            $row = $configs->get($levelId);
            $configMap[(string) $levelId] = $this->formatConfig($row);
        }

        return [
            'user_id' => $userId,
            'types' => $this->typeOptions(),
            'areas' => $areas,
            'configs' => $configMap,
            'summary' => $this->buildSummary($areas, $configs),
        ];
    }

    public function saveConfig(int $userId, array $data): array
    {
        if (!UserModel::find($userId)) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $levelId = (int) ($data['level_id'] ?? 0);
        if ($levelId <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '请选择广告位等级');
        }

        $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
        $payload = $this->rowsToPayload($rows);

        UserAdRatioConfigModel::updateOrCreate(
            ['user_id' => $userId, 'level_id' => $levelId],
            array_merge($payload, [
                'apply_to_subordinates' => (int) ($data['apply_to_subordinates'] ?? 0),
            ])
        );

        return $this->getBundle($userId);
    }

    public function setEnabled(int $userId, bool $enabled): array
    {
        if (!UserModel::find($userId)) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        if (!$enabled) {
            UserAdRatioConfigModel::where('user_id', $userId)->get()->each(function (UserAdRatioConfigModel $config): void {
                $config->update([
                    'ratio' => [],
                    'profit_rate' => [],
                    'ad_cycle' => [],
                ]);
            });
        }

        return $this->getBundle($userId);
    }

    /** @return array<int, array{value: int, label: string}> */
    private function typeOptions(): array
    {
        $options = [];
        foreach (AdConfigModel::$typeMap as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveAreas(): array
    {
        $levels = AdConfigModel::query()
            ->orderBy('sort')
            ->orderBy('level_id')
            ->get();

        $areas = [];
        foreach (self::AREA_KEYS as $type => $key) {
            $matched = $levels->first(fn (AdConfigModel $row) => (int) $row->type === $type);
            $areas[] = [
                'key' => $key,
                'type' => $type,
                'level_id' => $matched ? (int) $matched->level_id : 0,
                'name' => AdConfigModel::$typeMap[$type] ?? '未知类型',
            ];
        }

        return $areas;
    }

    private function formatConfig(?UserAdRatioConfigModel $row): array
    {
        if (!$row) {
            return [
                'apply_to_subordinates' => 0,
                'rows' => $this->defaultRows(),
                'enabled' => 0,
                'updated_at' => null,
            ];
        }

        return [
            'apply_to_subordinates' => (int) $row->apply_to_subordinates,
            'rows' => $this->modelToRows($row),
            'enabled' => $this->hasPositiveRatio($row) ? 1 : 0,
            'updated_at' => $row->updated_at,
        ];
    }

    /** @return array<int, array<string, string>> */
    private function defaultRows(): array
    {
        return [
            ['ratio' => '', 'profit_rate' => '', 'cycle_value' => '', 'cycle_unit' => 'hour'],
            ['ratio' => '', 'profit_rate' => '', 'cycle_value' => '', 'cycle_unit' => 'hour'],
            ['ratio' => '', 'profit_rate' => '', 'cycle_value' => '', 'cycle_unit' => 'hour'],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function modelToRows(UserAdRatioConfigModel $row): array
    {
        $ratios = array_values((array) $row->ratio);
        $profits = array_values((array) $row->profit_rate);
        $cycles = array_values((array) $row->ad_cycle);
        $count = max(count($ratios), count($profits), count($cycles));

        if ($count === 0) {
            return $this->defaultRows();
        }

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $cycle = is_array($cycles[$i] ?? null) ? $cycles[$i] : [];
            $rows[] = [
                'ratio' => (string) ($ratios[$i] ?? ''),
                'profit_rate' => (string) ($profits[$i] ?? ''),
                'cycle_value' => (string) ($cycle['value'] ?? ''),
                'cycle_unit' => (string) ($cycle['unit'] ?? 'hour'),
            ];
        }

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function rowsToPayload(array $rows): array
    {
        $ratio = [];
        $profitRate = [];
        $adCycle = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ratio[] = (string) ($row['ratio'] ?? '');
            $profitRate[] = (string) ($row['profit_rate'] ?? '');
            $adCycle[] = [
                'value' => (string) ($row['cycle_value'] ?? ''),
                'unit' => (string) ($row['cycle_unit'] ?? 'hour'),
            ];
        }

        return [
            'ratio' => $ratio,
            'profit_rate' => $profitRate,
            'ad_cycle' => $adCycle,
        ];
    }

    /** @param \Illuminate\Support\Collection<int, UserAdRatioConfigModel> $configs */
    private function buildSummary(array $areas, $configs): array
    {
        $enabledConfigs = $configs->filter(fn (UserAdRatioConfigModel $config) => $this->hasPositiveRatio($config));
        $primary = $enabledConfigs->first();

        $slotNames = [];
        foreach ($areas as $area) {
            $levelId = (int) $area['level_id'];
            $config = $configs->get($levelId);
            if ($config && $this->hasPositiveRatio($config)) {
                $slotNames[] = (string) $area['name'];
            }
        }

        $firstRatio = '';
        if ($primary) {
            $ratios = array_values(array_filter(array_map('strval', (array) $primary->ratio), fn ($v) => $v !== ''));
            $firstRatio = $ratios[0] ?? '';
        }

        return [
            'enabled' => $primary !== null,
            'ratio_text' => $firstRatio !== '' ? "{$firstRatio}%" : '-',
            'slots_text' => $slotNames !== [] ? implode('/', $slotNames) : '-',
            'scope_text' => $enabledConfigs->contains(fn (UserAdRatioConfigModel $c) => (int) $c->apply_to_subordinates === 1)
                ? '代理线'
                : '个人',
            'total' => $enabledConfigs->count(),
        ];
    }

    private function hasPositiveRatio(UserAdRatioConfigModel $config): bool
    {
        return array_filter(
            array_map('floatval', (array) $config->ratio),
            fn (float $value) => $value > 0
        ) !== [];
    }
}
