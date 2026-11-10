<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use app\model\member\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityRecordModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_activity_record';

    protected $fillable = [
        'is_virtual', 'activity_id', 'user_id', 'from_user_id', 'before_num', 'recharge_amount',
        'reward', 'reward_type', 'trigger_type', 'status', 'password', 'recharge_bonus_id',
        'returned_at', 'reward_multiplier', 'settle_date',
    ];

    public static array $statusMap = [0 => '待领取', 1 => '已领取', 2 => '已退回'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_activity_record', 'comment' => '活动参与记录表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'is_virtual' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '虚拟用户'],
                'activity_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '活动ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'from_user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '来源用户ID'],
                'before_num' => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'nullable' => true, 'comment' => '奖励前金额'],
                'recharge_amount' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '充值金额'],
                'reward' => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '奖励金额'],
                'reward_type' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '奖励类型'],
                'trigger_type' => ['type' => 'integer', 'default' => 0, 'comment' => '触发类型'],
                'status' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '状态'],
                'password' => ['type' => 'string', 'length' => 6, 'default' => '', 'comment' => '活动密码'],
                'recharge_bonus_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '充值送金门槛ID'],
                'returned_at' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '退回时间(unix)'],
                'reward_multiplier' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 1, 'comment' => '奖金倍数'],
                'settle_date' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '结算日期YYYYMMDD'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id']], ['columns' => ['activity_id']], ['columns' => ['status']],
            ],
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(UserModel::class, 'user_id'); }
    public function activity(): BelongsTo { return $this->belongsTo(ActivityModel::class, 'activity_id'); }
}
