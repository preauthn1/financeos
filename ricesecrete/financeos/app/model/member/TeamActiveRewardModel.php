<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class TeamActiveRewardModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_team_active_reward';

    protected $fillable = [
        'user_id', 'level1_user', 'level1_active', 'level2_active', 'level3_active',
        'from_type', 'sum_active', 'reward_level', 'reward_amount', 'reward_times', 'status',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'me_team_active_reward', 'comment' => '团队活跃奖励表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'level1_user' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '一级下级人数'],
                'level1_active' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '一级活跃人数'],
                'level2_active' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '二级活跃人数'],
                'level3_active' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '三级活跃人数'],
                'from_type' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 3, 'comment' => '触发类型'],
                'sum_active' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '总活跃人数'],
                'reward_level' => ['type' => 'string', 'length' => 6, 'default' => '0', 'comment' => '奖励等级'],
                'reward_amount' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '奖励金额'],
                'reward_times' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '奖励次数'],
                'status' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '0待领取 1已领取'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['user_id', 'status']]],
        ];
    }
}
