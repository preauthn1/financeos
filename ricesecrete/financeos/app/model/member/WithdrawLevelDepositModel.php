<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class WithdrawLevelDepositModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_withdraw_level_deposit';

    protected $fillable = [
        'user_id', 'target_level', 'amount', 'return_at', 'status', 'returned_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'target_level' => 'integer',
        'status' => 'integer',
        'return_at' => 'integer',
        'returned_at' => 'integer',
    ];

    public const STATUS_ACTIVE = 0;
    public const STATUS_RETURNED = 1;

    public static function tableSchema(): array
    {
        return [
            'table' => 'me_withdraw_level_deposit',
            'comment' => '提现等级保证金记录',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'target_level' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '目标等级'],
                'amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '保证金金额'],
                'return_at' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '应返还时间'],
                'status' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '0质押中 1已返还'],
                'returned_at' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '实际返还时间'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['user_id']], ['columns' => ['status']]],
        ];
    }
}
