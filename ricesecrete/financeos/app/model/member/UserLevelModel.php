<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLevelModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_user_level';

    protected $fillable = [
        'user_id', 'type', 'from_level', 'to_level', 'amount',
        'duration_days', 'duration_type', 'expired_at',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_level',
            'comment' => '等级变更历史',
            'columns' => [
                'id'         => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'    => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'type'           => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => 'VIP类型:0普通 1体验区 2活动专区'],
                'from_level'     => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '原等级'],
                'to_level'       => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '新等级'],
                'amount'         => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '花费金额'],
                'duration_days'  => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '有效天数'],
                'duration_type'  => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '有效类型'],
                'expired_at'     => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '到期时间(unix)'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}

