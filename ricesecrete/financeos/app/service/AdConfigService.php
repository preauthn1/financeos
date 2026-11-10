<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\ad\AdConfigModel;
use app\support\DateTimeFormat;
use app\support\ErrorCode;
use support\Redis;

class AdConfigService
{
    private const CACHE_KEY = 'ad_config:levels';

    public function __construct(private ?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    public function list(int $page, int $limit, array $filters = []): array
    {
        $query = AdConfigModel::query();

        if (!empty($filters['keyword'])) {
            $keyword = (string) $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('level_name', 'like', "%{$keyword}%")
                    ->orWhere('level_id', $keyword);
            });
        }
        if (isset($filters['type']) && $filters['type'] !== '') {
            $query->where('type', (int) $filters['type']);
        }
        if (isset($filters['is_show']) && $filters['is_show'] !== '') {
            $query->where('is_show', (int) $filters['is_show']);
        }

        $total = (clone $query)->count();
        $items = $query->orderBy('sort')->orderBy('level_id')
            ->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(fn (AdConfigModel $row) => $this->formatItem($row));

        return ['total' => $total, 'items' => $items];
    }

    public function show(int $id): array
    {
        $item = AdConfigModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '广告位配置不存在');
        }

        return $this->formatItem($item, true);
    }

    public function create(array $data): array
    {
        $payload = $this->normalizePayload($data);
        $levelId = (int) ($payload['level_id'] ?? 0);
        if ($levelId <= 0) {
            $levelId = (int) AdConfigModel::max('level_id') + 1;
            $payload['level_id'] = $levelId;
        }
        if (AdConfigModel::where('level_id', $levelId)->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '广告位等级已存在');
        }

        $now = time();
        $item = AdConfigModel::create(array_merge($payload, [
        ]));
        $this->clearCache();

        return $this->formatItem($item, true);
    }

    public function update(int $id, array $data): array
    {
        $item = AdConfigModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '广告位配置不存在');
        }

        $payload = $this->normalizePayload($data, false);
        if (isset($payload['level_id']) && (int) $payload['level_id'] !== (int) $item->level_id) {
            $exists = AdConfigModel::where('level_id', (int) $payload['level_id'])
                ->where('id', '!=', $id)->exists();
            if ($exists) {
                throw new BusinessException(ErrorCode::VALIDATION_FAILED, '广告位等级已存在');
            }
        }

        $item->fill($payload)->save();
        $this->clearCache();

        return $this->formatItem($item->fresh(), true);
    }

    public function delete(int $id): void
    {
        $item = AdConfigModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '广告位配置不存在');
        }
        $item->delete();
        $this->clearCache();
    }

    public function toggle(int $id, string $field, int $value): array
    {
        if (!in_array($field, ['is_show', 'is_can_buy'], true)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '不支持的字段');
        }

        $item = AdConfigModel::find($id);
        if (!$item) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '广告位配置不存在');
        }

        $item->update([
            $field => $value ? 1 : 0,
        ]);
        $this->clearCache();

        return $this->formatItem($item->fresh());
    }

    /** @return array<int, array<string, mixed>> */
    public function runtimeLevels(bool $visibleOnly = true): array
    {
        try {
            $cached = Redis::get(self::CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                $levels = json_decode($cached, true);
                if (is_array($levels)) {
                    return $visibleOnly
                        ? array_values(array_filter($levels, fn ($l) => (int) ($l['is_show'] ?? 1) === 1))
                        : $levels;
                }
            }
        } catch (\Throwable) {
        }

        $query = AdConfigModel::query()->orderBy('sort')->orderBy('level_id');
        if ($visibleOnly) {
            $query->where('is_show', 1);
        }

        $levels = $query->get()->map(fn (AdConfigModel $row) => self::toRuntimeLevel($row))->values()->all();

        try {
            Redis::setex(self::CACHE_KEY, 300, json_encode($levels, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
        }

        return $levels;
    }

    /** @return array<string, mixed> */
    public static function toRuntimeLevel(AdConfigModel $row): array
    {
        return [
            'id' => (int) $row->id,
            'level_id' => (int) $row->level_id,
            'name' => (string) $row->level_name,
            'level_name' => (string) $row->level_name,
            'type' => (int) $row->type,
            'price' => (float) $row->price,
            'price_text' => (string) $row->price_text,
            'invite_num' => (int) $row->invite_num,
            'active_num' => (int) $row->active_num,
            'invite_num_amount' => (float) $row->invite_num_amount,
            'income' => (float) $row->income,
            'income_max' => (float) $row->income_max,
            'income_text' => (string) $row->income_text,
            'active_income' => (float) $row->active_income,
            'invest_income_text' => (string) $row->invest_income_text,
            'invest_rate' => (float) $row->invest_rate,
            'excess_invest_rate' => (array) ($row->excess_invest_rate ?? []),
            'amount_range_inventory' => (array) ($row->amount_range_inventory ?? []),
            'active_rate' => (float) $row->active_rate,
            'invest_max_rate' => (float) $row->invest_max_rate,
            'invest_num' => (int) $row->invest_num,
            'invest_profit' => (float) $row->invest_profit,
            'fee_rate' => (float) $row->fee_rate,
            'invest_day' => (float) $row->invest_day,
            'invest_period_type' => (string) $row->invest_period_type,
            'invest_password' => (string) $row->invest_password,
            'password_expired_at' => (int) $row->password_expired_at,
            'task_num' => (int) $row->task_num,
            'limit_unlock_num' => (int) $row->limit_unlock_num,
            'limit_unlock_num_text' => (string) $row->limit_unlock_num_text,
            'level_at' => (int) $row->level_at,
            'level_time_text' => (string) $row->level_time_text,
            'recharge_rebate' => (string) $row->recharge_rebate,
            'task_rebate' => (string) $row->task_rebate,
            'again_charge_percent' => (int) $row->again_charge_percent,
            'again_charge_cap' => (int) $row->again_charge_cap,
            'withdraw_trx_min_amount' => (float) $row->withdraw_trx_min_amount,
            'withdraw_usdt_min_amount' => (float) $row->withdraw_usdt_min_amount,
            'withdraw_max_usdt' => (float) $row->withdraw_max_usdt,
            'is_show' => (int) $row->is_show,
            'is_can_buy' => (int) $row->is_can_buy,
            'image_url' => (string) $row->image_url,
            'sort' => (int) $row->sort,
            'withdraw_week' => (string) $row->withdraw_week,
            'unlock_time_start' => (string) $row->unlock_time_start,
            'unlock_time_end' => (string) $row->unlock_time_end,
            'unlock_time_disabled_tips' => (string) $row->unlock_time_disabled_tips,
            'password_refresh_frequency' => (int) $row->password_refresh_frequency,
            'session_count' => (int) $row->session_count,
        ];
    }

    public function typeOptions(): array
    {
        $options = [];
        foreach (AdConfigModel::$typeMap as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }

    /** @return array<int, array<string, mixed>> */
    public function passwordGroups(bool $autoRefreshExpired = true): array
    {
        if ($autoRefreshExpired) {
            $this->refreshExpiredPasswords();
        }

        $levels = AdConfigModel::query()
            ->where('is_show', 1)
            ->orderBy('type')
            ->orderBy('sort')
            ->get(['id', 'level_id', 'level_name', 'type', 'invest_password', 'password_expired_at', 'password_refresh_frequency']);

        $groups = [];
        foreach ($levels as $level) {
            $type = (int) $level->type;
            if (!isset($groups[$type])) {
                $expireAt = (int) $level->password_expired_at;
                $groups[$type] = $this->buildPasswordGroup($type, $level, $expireAt);
            }
            $groups[$type]['levels'][] = [
                'id' => (int) $level->id,
                'level_id' => (int) $level->level_id,
                'level_name' => (string) $level->level_name,
            ];
        }

        return array_values($groups);
    }

    public function updatePasswordByType(int $type, ?string $password = null, int $minutes = 0): array
    {
        if (!array_key_exists($type, AdConfigModel::$typeMap)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '广告位类型无效');
        }

        $password = $password ?: (string) random_int(100000, 999999);
        if (!preg_match('/^\d{6}$/', $password)) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '编码必须是6位数字');
        }

        $update = [
            'invest_password' => $password,
            'password_refresh_frequency' => max(0, $minutes),
            'password_expired_at' => $minutes > 0 ? time() + $minutes * 60 : 0,
        ];

        $count = AdConfigModel::where('type', $type)->update($update);
        if ($count <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_FAILED, '该类型下没有可更新的广告位');
        }

        $this->clearCache();

        return [
            'type' => $type,
            'updated' => $count,
            'invest_password' => $password,
            'password_expired_at' => $update['password_expired_at'],
            'password_expire_time_str' => $this->formatTimeLeft($update['password_expired_at'] - time()),
        ];
    }

    public function getPopupDialog(): array
    {
        $raw = $this->settingService->getValue('finance_product_data', []);
        $parsed = \app\support\FinanceProductPopupConfig::parseStored($raw);

        return [
            'items' => \app\support\FinanceProductPopupConfig::normalizeItems($parsed['items']),
            'style' => $parsed['style'],
        ];
    }

    public function savePopupDialog(array $items): array
    {
        $existing = \app\support\FinanceProductPopupConfig::parseStored(
            $this->settingService->getValue('finance_product_data', [])
        );
        $normalizedItems = \app\support\FinanceProductPopupConfig::normalizeItems($items);
        $payload = [
            'items' => $normalizedItems,
            'style' => $existing['style'],
        ];

        $this->saveSetting('finance_product_data', $payload, '广告位购买弹窗文案');

        return $payload;
    }

    public function getPlatformScale(): array
    {
        return $this->settingService->getValue('platform_scale_data', [
            'daily_impressions' => '',
            'daily_data_volume' => '',
            'advertiser_bid_amount' => '',
            'direct_publishers' => '',
            'office_locations' => '',
            'data_centers' => '',
        ]);
    }

    public function savePlatformScale(array $data): array
    {
        $payload = [
            'daily_impressions' => (string) ($data['daily_impressions'] ?? ''),
            'daily_data_volume' => (string) ($data['daily_data_volume'] ?? ''),
            'advertiser_bid_amount' => (string) ($data['advertiser_bid_amount'] ?? ''),
            'direct_publishers' => (string) ($data['direct_publishers'] ?? ''),
            'office_locations' => (string) ($data['office_locations'] ?? ''),
            'data_centers' => (string) ($data['data_centers'] ?? ''),
        ];
        $this->saveSetting('platform_scale_data', $payload, '平台规模配置');

        return $payload;
    }

    public function getOverdueDialog(): array
    {
        return $this->settingService->getValue('finance_product_overdue', [
            'countdown' => 60,
            'finance_product_button_count_down' => 30,
            'finance_product_yes_can_click' => 1,
            'finance_product_no_can_click' => 1,
            'finance_product_dialog_count_down' => 10,
            'finance_product_dialog_btn_cancel' => 5,
        ]);
    }

    public function saveOverdueDialog(array $data): array
    {
        $payload = [
            'countdown' => (int) ($data['countdown'] ?? 60),
            'finance_product_button_count_down' => (int) ($data['finance_product_button_count_down'] ?? 30),
            'finance_product_yes_can_click' => (int) ($data['finance_product_yes_can_click'] ?? 1),
            'finance_product_no_can_click' => (int) ($data['finance_product_no_can_click'] ?? 1),
            'finance_product_dialog_count_down' => (int) ($data['finance_product_dialog_count_down'] ?? 10),
            'finance_product_dialog_btn_cancel' => (int) ($data['finance_product_dialog_btn_cancel'] ?? 5),
        ];
        $this->saveSetting('finance_product_overdue', $payload, '超额匹配弹窗配置');
        Redis::del('setting:finance_product_overdue');

        return $payload;
    }

    /** @return array<int, array<string, mixed>> */
    public function getTaskSessions(): array
    {
        $sessions = $this->settingService->getValue('finance_product_task_sessions', []);
        if (!is_array($sessions)) {
            return [];
        }

        return array_values(array_map(static function (array $session): array {
            $levelTypes = (string) ($session['level_types'] ?? ',1,2,3,4,');
            $levelTypeList = array_values(array_filter(
                array_map('intval', explode(',', trim($levelTypes, ','))),
                static fn ($type) => $type > 0
            ));

            return array_merge($session, [
                'level_type_list' => $levelTypeList,
            ]);
        }, $sessions));
    }

    /** @param array<int, array<string, mixed>> $sessions */
    public function saveTaskSessions(array $sessions): array
    {
        $normalized = [];
        $nextId = 1;
        foreach ($sessions as $session) {
            $startedAt = (string) ($session['started_at'] ?? $session['start_time'] ?? '00:00:00');
            $endedAt = (string) ($session['ended_at'] ?? $session['end_time'] ?? '23:59:59');
            $levelTypes = $this->normalizeLevelTypes($session['level_types'] ?? $session['level_type_list'] ?? ',1,2,3,4,');

            $normalized[] = [
                'id' => (int) ($session['id'] ?? $nextId),
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'level_types' => $levelTypes,
                'status' => (int) ($session['status'] ?? 1),
                'sort' => (int) ($session['sort'] ?? $nextId),
            ];
            $nextId = max($nextId + 1, (int) $normalized[array_key_last($normalized)]['id'] + 1);
        }

        usort($normalized, static fn ($a, $b) => ($a['sort'] <=> $b['sort']) ?: strcmp($a['started_at'], $b['started_at']));
        $this->saveSetting('finance_product_task_sessions', $normalized, '广告位时段配置');
        Redis::del('setting:finance_product_task_sessions');

        return $normalized;
    }

    private function normalizeLevelTypes(mixed $value): string
    {
        if (is_array($value)) {
            $types = array_values(array_unique(array_filter(array_map('intval', $value), static fn ($type) => $type > 0)));
            sort($types);

            return $types === [] ? '' : ',' . implode(',', $types) . ',';
        }

        $levelTypes = trim((string) $value);
        if ($levelTypes === '') {
            return '';
        }

        $types = array_values(array_unique(array_filter(
            array_map('intval', explode(',', trim($levelTypes, ','))),
            static fn ($type) => $type > 0
        )));
        sort($types);

        return $types === [] ? '' : ',' . implode(',', $types) . ',';
    }

    private function refreshExpiredPasswords(): void
    {
        foreach (AdConfigModel::$typeMap as $type => $label) {
            try {
                $sample = AdConfigModel::where('type', $type)->where('is_show', 1)->first();
                if (!$sample) {
                    continue;
                }
                $expireAt = (int) $sample->password_expired_at;
                $frequency = (int) $sample->password_refresh_frequency;
                if ($frequency > 0 && $expireAt > 0 && $expireAt <= time()) {
                    $this->updatePasswordByType((int) $type, null, $frequency);
                }
            } catch (\Throwable) {
                // continue
            }
        }
    }

    private function buildPasswordGroup(int $type, AdConfigModel $level, int $expireAt): array
    {
        return [
            'type' => $type,
            'type_name' => AdConfigModel::$typeMap[$type] ?? '未知类型',
            'invest_password' => (string) $level->invest_password,
            'password_expired_at' => $expireAt,
            'password_expire_time' => $expireAt > 0 ? date('Y-m-d H:i:s', $expireAt) : '',
            'password_expire' => $expireAt > 0 && $expireAt <= time() ? 1 : 0,
            'password_expire_time_str' => $this->formatTimeLeft($expireAt - time()),
            'password_refresh_frequency' => (int) $level->password_refresh_frequency,
            'levels' => [],
        ];
    }

    private function formatTimeLeft(int $seconds): string
    {
        if ($seconds <= 0) {
            return '已过期';
        }
        $hour = intdiv($seconds, 3600);
        $min = intdiv($seconds % 3600, 60);
        $sec = $seconds % 60;
        if ($hour > 0) {
            return "{$hour}小时{$min}分钟{$sec}秒";
        }

        return "{$min}分钟{$sec}秒";
    }

    private function saveSetting(string $key, mixed $value, string $description): void
    {
        $setting = \app\model\sys\SettingModel::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => $value]);
        } else {
            \app\model\sys\SettingModel::create([
                'key' => $key,
                'value' => $value,
                'description' => $description,
            ]);
        }
        Redis::del("setting:{$key}");
    }

    private function formatItem(AdConfigModel $row, bool $full = false): array
    {
        $data = self::toRuntimeLevel($row);
        $data['type_text'] = AdConfigModel::$typeMap[(int) $row->type] ?? '未知';
        $data['created_at'] = DateTimeFormat::datetime($row->created_at);
        $data['updated_at'] = DateTimeFormat::datetime($row->updated_at);

        if ($full) {
            return $data;
        }

        unset($data['amount_range_inventory'], $data['excess_invest_rate']);
        return $data;
    }

    /** @return array<string, mixed> */
    private function normalizePayload(array $data, bool $isCreate = true): array
    {
        $allowed = array_fill_keys((new AdConfigModel())->getFillable(), true);
        $payload = array_intersect_key($data, $allowed);

        foreach (['excess_invest_rate', 'amount_range_inventory'] as $jsonField) {
            if (!array_key_exists($jsonField, $payload)) {
                continue;
            }
            if (is_string($payload[$jsonField])) {
                $decoded = json_decode($payload[$jsonField], true);
                $payload[$jsonField] = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($payload[$jsonField])) {
                $payload[$jsonField] = [];
            }
        }

        if (isset($payload['excess_invest_rate'], $payload['invest_num'])) {
            $investNum = max(1, (int) $payload['invest_num']);
            $payload['excess_invest_rate'] = array_slice(
                array_values($payload['excess_invest_rate']),
                0,
                $investNum
            );
        }

        if ($isCreate) {
            $payload['sort'] = (int) ($payload['sort'] ?? ((int) AdConfigModel::max('sort') + 1));
            $payload['is_show'] = (int) ($payload['is_show'] ?? 1);
            $payload['is_can_buy'] = (int) ($payload['is_can_buy'] ?? 1);
            $payload['invest_period_type'] = (string) ($payload['invest_period_type'] ?? 'hour');
            $payload['recharge_rebate'] = (string) ($payload['recharge_rebate'] ?? '[0,0,0]');
            $payload['task_rebate'] = (string) ($payload['task_rebate'] ?? '[0,0,0]');
            $payload['withdraw_week'] = (string) ($payload['withdraw_week'] ?? '1,2,3,4,5');
        }

        return $payload;
    }

    private function clearCache(): void
    {
        try {
            Redis::del(self::CACHE_KEY);
        } catch (\Throwable) {
        }
    }
}
