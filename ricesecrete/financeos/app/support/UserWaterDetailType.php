<?php

declare(strict_types=1);

namespace app\support;

final class UserWaterDetailType
{
    public const ADMIN_ADJUST = 0;
    public const RECHARGE = 1;
    public const WITHDRAW = 2;
    public const STOCK_BUY = 12;
    public const AI_TRUST = 13;
    public const AI_TRUST_PROFIT = 14;
    public const SETTLE_PROFIT = 16;
    public const TRANSFER_OUT = 22;
    public const TRANSFER_IN = 23;
    public const PROMOTION_REWARD = 24;
    public const TRIAL_BONUS_RETURN = 29;
    public const RECHARGE_BONUS = 26;
    public const TRIAL_BONUS = 28;
    public const RECHARGE_BONUS_COMMISSION = 32;
    public const TRADE_DIVIDEND = 33;
    public const INVITE_VALID_USER = 34;
    public const SIGN_IN_POOL = 35;
    public const INVEST_COMMISSION = 36;

    /** @var array<int, string> */
    public const NAMES = [
        0 => '后台手动调整金额',
        1 => '用户充值',
        2 => '提现申请',
        3 => '任务返佣',
        4 => '首充送款',
        5 => '抽奖活动',
        6 => '签到活动',
        7 => '升级返佣',
        8 => '分享奖励',
        9 => '任务返利-1级别',
        10 => '任务返利-2级别',
        11 => '任务返利-3级别',
        12 => '库存买入',
        13 => 'AI智能托管',
        14 => 'AI智能托管收益',
        15 => '当日直推奖励',
        16 => '收益结算',
        17 => '升级会员',
        19 => '团队活跃奖',
        20 => '系统自动修正',
        21 => '奖励发放错误',
        22 => '转账给其他用户',
        23 => '收到其他用户转账',
        24 => '连续推广奖励',
        25 => '领取佣金池佣金',
        26 => '充值送金',
        27 => '领取团队活跃奖励',
        28 => '体验券领取',
        29 => '体验券到期回收',
        30 => '交易费率',
        31 => '领取运营中心总奖金',
        32 => '充值送金下级返佣',
        33 => '每日交易分红',
        34 => '邀请有效用户奖励',
        35 => '签到奖励(佣金池)',
        36 => 'AI托管下级返佣',
    ];

    public static function label(?int $detailType): string
    {
        if ($detailType === null) {
            return '';
        }

        return self::NAMES[$detailType] ?? (string) $detailType;
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
}
