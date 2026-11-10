<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class TeamActiveRewardV2Model extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_team_active_reward_v2';

    protected $fillable = [
        'user_id', 'level1_user', 'level1_active', 'level2_active', 'level3_active',
        'from_type', 'sum_active', 'reward_level', 'reward_amount', 'received_at', 'status',
        'phase', 'index', 'config', 'trigger_type', 'trigger_level1_num', 'trigger_active_num',
    ];

    protected $casts = ['config' => 'array'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'me_team_active_reward_v2', 'comment' => '团队活跃奖励V2表',
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
                'received_at' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '领取时间'],
                'status' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '0待领取 1已领取'],
                'phase' => ['type' => 'tinyInteger', 'nullable' => true, 'comment' => '阶段'],
                'index' => ['type' => 'integer', 'default' => 0, 'comment' => '当前阶段索引'],
                'config' => ['type' => 'json', 'nullable' => true, 'comment' => '奖励配置快照'],
                'trigger_type' => ['type' => 'tinyInteger', 'nullable' => true, 'comment' => '触发类型'],
                'trigger_level1_num' => ['type' => 'integer', 'nullable' => true, 'comment' => '直推人数门槛'],
                'trigger_active_num' => ['type' => 'integer', 'nullable' => true, 'comment' => '活跃人数门槛'],
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
