<?php

declare(strict_types=1);

namespace app\service;

use app\model\sys\SettingModel;
use support\Redis;

class FinanceProductConfigService
{
    private const KEYS = [
        'finance_product_levels' => '广告位等级配置(JSON数组)',
        'finance_product_task_sessions' => '广告位时段配置(JSON数组)',
        'finance_product_password' => '全局广告位编码',
        'finance_product_level_passwords' => '各等级编码(JSON对象)',
        'finance_product_overdue' => '超额匹配弹窗配置',
        'level_upgrade_type' => '升级模式:1免费 2扣款',
        'credit_score_times' => '信用分预支倍数',
        'api_sign_secret' => 'API签名密钥',
    ];

    public function all(): array
    {
        $items = [];
        foreach (self::KEYS as $key => $description) {
            $setting = SettingModel::where('key', $key)->first();
            $items[] = [
                'key' => $key,
                'description' => $description,
                'value' => $setting?->value,
                'exists' => (bool) $setting,
            ];
        }
        return $items;
    }

    public function get(string $key): mixed
    {
        if (!isset(self::KEYS[$key])) {
            throw new \app\exception\BusinessException(\app\support\ErrorCode::NOT_FOUND, '未知配置项');
        }
        return (new SettingService())->getValue($key, $this->defaultValue($key));
    }

    public function save(string $key, mixed $value): void
    {
        if (!isset(self::KEYS[$key])) {
            throw new \app\exception\BusinessException(\app\support\ErrorCode::NOT_FOUND, '未知配置项');
        }

        $setting = SettingModel::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => $value]);
        } else {
            SettingModel::create([
                'key' => $key,
                'value' => $value,
                'description' => self::KEYS[$key],
            ]);
        }
        Redis::del("setting:{$key}");
    }

    public function seedDefaults(): void
    {
        foreach ($this->defaults() as $key => $payload) {
            $exists = SettingModel::where('key', $key)->exists();
            if ($exists) {
                continue;
            }
            SettingModel::create([
                'key' => $key,
                'value' => $payload['value'],
                'description' => $payload['description'],
            ]);
        }
    }

    /** @return array<string, array{value:mixed,description:string}> */
    public function defaults(): array
    {
        return [
            'finance_product_levels' => [
                'description' => self::KEYS['finance_product_levels'],
                'value' => [
                    [
                        'level_id' => 1,
                        'name' => '普通区',
                        'type' => 2,
                        'price' => 100,
                        'invest_num' => 3,
                        'invest_rate' => 30,
                        'invest_max_rate' => 50,
                        'active_rate' => 2,
                        'invest_profit' => 1.5,
                        'invest_day' => 1,
                        'invest_period_type' => 'day',
                        'fee_rate' => 0,
                        'limit_unlock_num' => 0,
                        'session_count' => 0,
                        'unlock_time_start' => '00:00:00',
                        'unlock_time_end' => '23:59:59',
                        'unlock_time_disabled_tips' => '目前无法购买',
                        'task_rebate' => '5,3,1',
                        'amount_range_inventory' => [
                            ['min' => 100, 'max' => 1000, 'stock' => 50, 'rate' => 30, 'profit' => 1.5, 'period' => 1, 'period_type' => 'day'],
                        ],
                        'excess_invest_rate' => [50, 80],
                    ],
                ],
            ],
            'finance_product_task_sessions' => [
                'description' => self::KEYS['finance_product_task_sessions'],
                'value' => [
                    ['id' => 1, 'started_at' => '00:00:00', 'ended_at' => '23:59:59', 'level_types' => ',1,2,3,4,'],
                ],
            ],
            'finance_product_password' => ['description' => self::KEYS['finance_product_password'], 'value' => '8888'],
            'finance_product_level_passwords' => ['description' => self::KEYS['finance_product_level_passwords'], 'value' => []],
            'finance_product_overdue' => [
                'description' => self::KEYS['finance_product_overdue'],
                'value' => [
                    'countdown' => 60,
                    'finance_product_button_count_down' => 30,
                    'finance_product_yes_can_click' => 1,
                    'finance_product_no_can_click' => 1,
                    'finance_product_dialog_count_down' => 10,
                    'finance_product_dialog_btn_cancel' => 5,
                ],
            ],
            'level_upgrade_type' => ['description' => self::KEYS['level_upgrade_type'], 'value' => 1],
            'credit_score_times' => ['description' => self::KEYS['credit_score_times'], 'value' => '1:0-100'],
            'api_sign_secret' => ['description' => self::KEYS['api_sign_secret'], 'value' => 'akz8VEU!kru6kgk9dhp'],
        ];
    }

    private function defaultValue(string $key): mixed
    {
        return $this->defaults()[$key]['value'] ?? null;
    }
}
