<?php

declare(strict_types=1);

namespace app\support;

final class ArticleCategoryDict
{
    public const CODE = 'article_category';

    /** @return array<int, array{label:string,value:string,sort:int}> */
    public static function defaultItems(): array
    {
        $map = [
            1 => '公司简介',
            2 => '帮助中心',
            3 => '提现提示',
            4 => 'AI智能托管规则',
            5 => '注册协议',
            6 => '推广规则',
            7 => '广告位规则',
            8 => '平台教程',
            9 => '签到规则',
            10 => '直推规则',
            11 => '充值规则',
            12 => '提现规则',
            13 => '拼多多转盘规则说明',
            14 => '信用分说明',
            15 => '个人佣金池的规则',
            16 => '连续推广奖励规则',
            17 => '团队活跃奖励规则',
            18 => '平台指南规则',
            19 => '代理运营中心规则',
            20 => '团队运营中心规则',
            21 => '城市运营中心规则',
        ];

        $items = [];
        foreach ($map as $value => $label) {
            $items[] = [
                'label' => $label,
                'value' => (string) $value,
                'sort' => (int) $value,
            ];
        }

        return $items;
    }
}
