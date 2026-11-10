<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBalanceModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_user_balance';

    protected $fillable = [
        'user_id', 'amount', 'period', 'period_hour', 'settle_date',
        'settle_count', 'settle_hour', 'status',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_balance',
            'comment' => '历史余额宝存储记录(已废弃)',
            'columns' => [
                'id'          => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'     => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'amount'      => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '存储金额'],
                'period'      => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '时间标识YYYYMMDDHH'],
                'period_hour' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '时段小时'],
                'settle_date' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '结算日期YYYYMMDD'],
                'settle_count' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '结算次数'],
                'settle_hour' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '已结算小时数'],
                'status'      => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '状态:1未提款 2已提款'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id']],
                ['columns' => ['status']],
                ['columns' => ['period']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}

