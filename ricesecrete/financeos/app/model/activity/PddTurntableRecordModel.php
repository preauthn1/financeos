<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class PddTurntableRecordModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_pdd_turntable_record';

    protected $fillable = [
        'user_id', 'turntable_id', 'prize_id', 'prize_name', 'amount', 'progress',
        'is_big_prize', 'is_receive', 'task_period',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_pdd_turntable_record', 'comment' => 'PDD抽奖记录表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'turntable_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '活动ID'],
                'prize_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '奖品ID'],
                'prize_name' => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '奖品名称'],
                'amount' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '奖品金额'],
                'progress' => ['type' => 'decimal', 'precision' => 5, 'scale' => 2, 'default' => 0, 'comment' => '进度百分比'],
                'is_big_prize' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '是否大奖'],
                'is_receive' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '是否已领取'],
                'task_period' => ['type' => 'integer', 'default' => 24, 'comment' => '任务周期(小时)'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['user_id', 'turntable_id']]],
        ];
    }
}
