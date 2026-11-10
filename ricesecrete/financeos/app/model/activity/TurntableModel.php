<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class TurntableModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_turntable';

    protected $fillable = [
        'status', 'type', 'prize', 'capital_type', 'register_num', 'invite_recharge',
        'is_default',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_turntable', 'comment' => '转盘抽奖活动表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'status' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '状态:1开启 2关闭'],
                'type' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '类型:1转盘 2红包'],
                'prize' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '奖品范围:1全局 2按VIP'],
                'capital_type' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '资金类型'],
                'register_num' => ['type' => 'integer', 'default' => 0, 'comment' => '注册赠送次数'],
                'invite_recharge' => ['type' => 'decimal', 'precision' => 20, 'scale' => 2, 'default' => 0, 'comment' => '邀请充值门槛'],
                'is_default' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '是否默认'],
            ],
        ];
    }
}
