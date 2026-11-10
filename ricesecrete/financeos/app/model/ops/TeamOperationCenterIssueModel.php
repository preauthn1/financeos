<?php

declare(strict_types=1);

namespace app\model\ops;

use app\model\Concerns\DefinesTableSchema;
use app\model\member\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamOperationCenterIssueModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'toc_operation_center_issue';

    protected $fillable = [
        'user_id', 'submit_user_id', 'center_id', 'type', 'status', 'issue',
        'dividend', 'rebate', 'fund', 'bonus', 'sub_count', 'sub_reward', 'dividend_item',
    ];

    public const STATUS_UNSETTLED = 1;
    public const STATUS_SETTLED = 2;
    public const STATUS_CLAIMED = 3;

    public static array $statusMap = [
        self::STATUS_UNSETTLED => '未结算',
        self::STATUS_SETTLED   => '已结算',
        self::STATUS_CLAIMED   => '已领取',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'toc_operation_center_issue', 'comment' => '运营中心周期结算表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'submit_user_id' => ['type' => 'unsignedBigInteger', 'nullable' => true, 'comment' => '提交人ID'],
                'center_id' => ['type' => 'unsignedBigInteger', 'nullable' => true, 'comment' => '运营中心ID'],
                'type' => ['type' => 'integer', 'comment' => '类型'],
                'status' => ['type' => 'integer', 'default' => 1, 'comment' => '状态:1未结算 2已结算 3已领取'],
                'issue' => ['type' => 'string', 'length' => 50, 'nullable' => true, 'comment' => '周期标识'],
                'dividend' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'nullable' => true, 'comment' => '团队交易日分红'],
                'rebate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'nullable' => true, 'comment' => '入金返佣'],
                'fund' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'nullable' => true, 'comment' => '发展基金'],
                'bonus' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'nullable' => true, 'comment' => '待领取奖金'],
                'sub_count' => ['type' => 'integer', 'nullable' => true, 'comment' => '下级运营中心数'],
                'sub_reward' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'nullable' => true, 'comment' => '推广奖励'],
                'dividend_item' => ['type' => 'string', 'length' => 200, 'nullable' => true, 'comment' => '分红明细'],
            ],
            'unique' => [['columns' => ['center_id', 'issue']]],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['status']], ['columns' => ['user_id']]],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(TeamOperationCenterModel::class, 'center_id');
    }
}
