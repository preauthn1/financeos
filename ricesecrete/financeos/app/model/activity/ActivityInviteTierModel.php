<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class ActivityInviteTierModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_invite_tier';

    protected $fillable = [
        'activity_id', 'invite_num', 'multiplier', 'winner_rate', 'status', 'sort',
    ];

    protected $casts = [
        'activity_id' => 'integer',
        'invite_num' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_invite_tier',
            'comment' => '邀请有效用户超额阶梯',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'activity_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '活动ID'],
                'invite_num' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '邀请人数门槛'],
                'multiplier' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 1, 'comment' => '奖金倍数'],
                'winner_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '获奖人数比例%'],
                'status' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '状态'],
                'sort' => ['type' => 'integer', 'default' => 0, 'comment' => '排序'],
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
