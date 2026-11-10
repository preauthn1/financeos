<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class TurntableCountModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_turntable_count';

    protected $fillable = [
        'user_id', 'from_user_id', 'turntable_id', 'remark', 'reg_num', 'num',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_turntable_count', 'comment' => '转盘抽奖次数表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'from_user_id' => ['type' => 'text', 'nullable' => true, 'comment' => '来源用户JSON'],
                'turntable_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '转盘ID'],
                'remark' => ['type' => 'text', 'nullable' => true, 'comment' => '备注'],
                'reg_num' => ['type' => 'integer', 'default' => 0, 'comment' => '注册次数'],
                'num' => ['type' => 'integer', 'default' => 0, 'comment' => '邀请次数'],
            ],
            'unique' => [['columns' => ['user_id', 'turntable_id']]],
        ];
    }
}
