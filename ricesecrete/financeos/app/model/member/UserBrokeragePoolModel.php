<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBrokeragePoolModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_user_brokerage_pool';

    protected $fillable = ['user_id', 'receivable', 'received'];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_brokerage_pool',
            'comment' => '佣金池',
            'columns' => [
                'id'         => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'    => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'receivable' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'unsigned' => true, 'default' => 0, 'comment' => '可领取'],
                'received'   => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'unsigned' => true, 'default' => 0, 'comment' => '已领取'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id']],
                ['columns' => ['created_at']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}

