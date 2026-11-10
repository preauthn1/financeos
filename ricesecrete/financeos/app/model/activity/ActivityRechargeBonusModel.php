<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class ActivityRechargeBonusModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_recharge_bonus';

    protected $fillable = [
        'activity_id', 'amount', 'rate', 'num', 'status', 'remark',
    ];

    protected $casts = [
        'activity_id' => 'integer',
        'status' => 'integer',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_recharge_bonus',
            'comment' => '充值送金阶梯配置',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'activity_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '活动ID'],
                'amount' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '充值门槛金额'],
                'rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '奖励比例%'],
                'num' => ['type' => 'unsignedInteger', 'default' => 1, 'comment' => '名额'],
                'status' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '状态:0关闭 1开启'],
                'remark' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '备注'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['activity_id']]],
        ];
    }
}
