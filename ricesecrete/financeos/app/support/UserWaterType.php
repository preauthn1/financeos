<?php

declare(strict_types=1);

namespace app\support;

final class UserWaterType
{
    public const TYPE_USER = 1;
    public const TYPE_RECHARGE = 2;
    public const TYPE_TYJ = 3;
    public const TYPE_FROZEN = 4;
    public const TYPE_EARNINGS = 6;
    public const TYPE_BALANCE = 7;
    public const TYPE_BROKERAGE_POOL = 8;
    public const TYPE_WITHDRAWAL = 9;

    /** @var array<int, string> */
    public const NAMES = [
        self::TYPE_USER => '基础钱包',
        self::TYPE_RECHARGE => '累计充值',
        self::TYPE_TYJ => '彩金钱包',
        self::TYPE_FROZEN => '冻结钱包',
        self::TYPE_EARNINGS => '总收益',
        self::TYPE_BALANCE => 'AI托管钱包(历史)',
        self::TYPE_BROKERAGE_POOL => '佣金池',
        self::TYPE_WITHDRAWAL => '提现钱包',
    ];

    /** 后台调整：用户表字段 => 流水钱包类型 */
    /** @var array<string, int> */
    public const ADMIN_WALLET_KEY_TO_TYPE = [
        'balance' => self::TYPE_USER,
        'recharge_balance' => self::TYPE_RECHARGE,
        'trial_balance' => self::TYPE_TYJ,
        'frozen_balance' => self::TYPE_FROZEN,
        'brokerage_balance' => self::TYPE_BROKERAGE_POOL,
    ];

    /** @var array<string, string> */
    public const ADMIN_WALLET_KEY_LABELS = [
        'balance' => '基础',
        'recharge_balance' => '累计充值',
        'trial_balance' => '彩金',
        'brokerage_balance' => '佣金池',
        'frozen_balance' => '冻结',
    ];

    public static function label(?int $type): string
    {
        if ($type === null) {
            return '';
        }

        return self::NAMES[$type] ?? (string) $type;
    }

    public static function waterTypeForWalletKey(string $walletKey): int
    {
        return self::ADMIN_WALLET_KEY_TO_TYPE[$walletKey] ?? self::TYPE_USER;
    }

    /** @return list<array{value: int, label: string}> */
    public static function options(): array
    {
        $result = [];
        foreach (self::NAMES as $value => $label) {
            $result[] = ['value' => $value, 'label' => $label];
        }

        return $result;
    }

    /** @return list<array{value: string, label: string}> */
    public static function adminWalletOptions(): array
    {
        $result = [];
        foreach (self::ADMIN_WALLET_KEY_LABELS as $value => $label) {
            $result[] = ['value' => $value, 'label' => $label];
        }

        return $result;
    }
}
