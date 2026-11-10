<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use app\model\Concerns\PartitionedLogTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVirtualLogModel extends Model
{
    use DefinesTableSchema;
    use PartitionedLogTable;

    protected $table = 'me_user_virtual_log';

    protected $fillable = [
        'user_id', 'admin_id', 'type', 'direction', 'title',
        'amount', 'before_balance', 'after_balance', 'remark',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_virtual_log',
            'comment' => '虚拟用户账变记录表',
            'columns' => [
                'id'             => ['type' => 'bigIncrements', 'comment' => '主键ID'],
                'user_id'        => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'admin_id'       => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '管理员ID'],
                'type'           => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '钱包类型'],
                'direction'      => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '方向:1增加 2减少'],
                'title'          => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '标题'],
                'amount'         => ['type' => 'decimal', 'precision' => 20, 'scale' => 8, 'default' => 0, 'comment' => '操作金额'],
                'before_balance' => ['type' => 'decimal', 'precision' => 20, 'scale' => 8, 'default' => 0, 'comment' => '操作前金额'],
                'after_balance'  => ['type' => 'decimal', 'precision' => 20, 'scale' => 8, 'default' => 0, 'comment' => '操作后金额'],
                'remark'         => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '备注'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id', 'type', 'created_at']],
                ['columns' => ['user_id', 'type', 'amount']],
                ['columns' => ['created_at']],
                ['columns' => ['amount']],
            ],
            'partition' => self::monthlyPartition('created_at', 'timestamp'),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
