<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class ActivityModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_activity';

    protected $fillable = [
        'title', 'thumb', 'started_at', 'ended_at', 'content', 'user_type', 'trigger_type',
        'type', 'reward_type', 'reward', 'times', 'day_times', 'day_times_type', 'user_amount',
        'num', 'password', 'expired_at', 'status', 'extra',
    ];

    protected $casts = ['extra' => 'array', 'status' => 'integer', 'type' => 'integer'];

    public static array $typeMap = [
        0 => '线下活动',
        1 => '充值活动',
        2 => '邀请活动',
        3 => '邀请充值',
        4 => '签到活动',
        7 => '邀请有效用户',
        8 => '充值送金',
        9 => '体验券',
    ];

    public const TYPE_INVITE_VALID = 7;
    public const TYPE_RECHARGE_BONUS = 8;
    public const TYPE_TRIAL_VOUCHER = 9;

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_activity', 'comment' => '活动表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'title' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '活动名称'],
                'thumb' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '活动图片'],
                'started_at' => ['type' => 'unsignedBigInteger', 'nullable' => true, 'comment' => '开始时间(unix)'],
                'ended_at' => ['type' => 'unsignedBigInteger', 'nullable' => true, 'comment' => '结束时间(unix)'],
                'content' => ['type' => 'text', 'nullable' => true, 'comment' => '活动介绍'],
                'user_type' => ['type' => 'string', 'length' => 200, 'default' => '', 'comment' => '参与用户类型'],
                'trigger_type' => ['type' => 'string', 'length' => 200, 'default' => '', 'comment' => '触发类型'],
                'type' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '活动类型'],
                'reward_type' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '奖励类型:1货币 2积分'],
                'reward' => ['type' => 'string', 'length' => 50, 'default' => '0', 'comment' => '奖励值'],
                'times' => ['type' => 'integer', 'default' => 0, 'comment' => '可领奖次数'],
                'day_times' => ['type' => 'integer', 'nullable' => true, 'comment' => '每日参与次数'],
                'day_times_type' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '每日次数类型'],
                'user_amount' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '金额门槛'],
                'num' => ['type' => 'unsignedInteger', 'default' => 1, 'comment' => '活动名额'],
                'password' => ['type' => 'string', 'length' => 6, 'default' => '', 'comment' => '活动密码'],
                'expired_at' => ['type' => 'decimal', 'precision' => 8, 'scale' => 2, 'default' => 0, 'comment' => '体验金过期天数'],
                'status' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '状态:0关闭 1开启'],
                'extra' => ['type' => 'json', 'nullable' => true, 'comment' => '扩展信息'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['status']], ['columns' => ['type']]],
        ];
    }
}
