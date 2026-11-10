<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use app\model\Concerns\PartitionedLogTable;
use app\model\sys\AdminModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWaterModel extends Model
{
    use DefinesTableSchema;
    use PartitionedLogTable;

    protected $table = 'me_user_water';

    protected $fillable = [
        'user_id', 'admin_id', 'order_no', 'title',
        'type', 'detail_type', 'direction',
        'amount', 'before_balance', 'after_balance', 'rate',
        'remark', 'status', 'is_virtual',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_water',
            'comment' => '用户资金流水表',
            'columns' => [
                'id'             => ['type' => 'bigIncrements', 'comment' => '主键ID'],
                'user_id'        => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'admin_id'       => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '操作管理员ID'],
                'order_no'       => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '关联订单ID'],
                'title'          => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '账单标题'],
                'type'           => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '钱包类型:1基础 2充值 3彩金 4冻结 6总收益 7AI托管(历史) 8佣金池 9提现'],
                'detail_type'    => ['type' => 'tinyInteger', 'unsigned' => true, 'nullable' => true, 'comment' => '明细类型'],
                'direction'      => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '收支方向:1收入 2支出'],
                'amount'         => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'unsigned' => false, 'default' => 0, 'comment' => '变动金额'],
                'before_balance' => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'unsigned' => false, 'default' => 0, 'comment' => '变动前余额'],
                'after_balance'  => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'unsigned' => false, 'default' => 0, 'comment' => '变动后余额'],
                'rate'           => ['type' => 'unsignedBigInteger', 'default' => 100, 'comment' => '流水倍率(%)'],
                'remark'         => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '备注'],
                'status'         => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '显示状态:0隐藏 1显示'],
                'is_virtual'     => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '虚拟流水:0否 1是'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id', 'type', 'created_at']],
                ['columns' => ['user_id', 'type', 'amount']],
                ['columns' => ['user_id', 'direction', 'type', 'detail_type']],
                ['columns' => ['user_id', 'type', 'detail_type', 'created_at']],
                ['columns' => ['user_id', 'detail_type']],
                ['columns' => ['type', 'created_at']],
                ['columns' => ['created_at']],
                ['columns' => ['amount']],
                ['columns' => ['order_no']],
                ['columns' => ['title']],
            ],
            'partition' => self::monthlyPartition('created_at', 'timestamp'),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminModel::class, 'admin_id');
    }
}
