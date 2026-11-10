<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class PromotionRewardDayModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_promotion_reward_day';

    protected $fillable = [
        'user_id', 'day', 'reward', 'status',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'me_promotion_reward_day', 'comment' => '连续推广签到奖励表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'day' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '连续天数'],
                'reward' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '奖励金额'],
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
