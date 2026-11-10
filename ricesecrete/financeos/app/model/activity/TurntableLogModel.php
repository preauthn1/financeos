<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use app\model\Concerns\PartitionedLogTable;
use Illuminate\Database\Eloquent\Model;

class TurntableLogModel extends Model
{
    use DefinesTableSchema;
    use PartitionedLogTable;

    protected $table = 'act_turntable_log';

    protected $fillable = ['user_id', 'prize_id', 'amount', 'name', 'type'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_turntable_log', 'comment' => '转盘抽奖记录表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'prize_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '奖品ID'],
                'amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '中奖金额'],
                'name' => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '奖品名称'],
                'type' => ['type' => 'integer', 'default' => 1, 'comment' => '次数类型'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['user_id']]],
            'partition' => self::monthlyPartition('created_at', 'timestamp'),
        ];
    }
}
