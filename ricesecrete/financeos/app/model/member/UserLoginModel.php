<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use app\model\Concerns\PartitionedLogTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginModel extends Model
{
    use DefinesTableSchema;
    use PartitionedLogTable;

    protected $table = 'me_user_login';

    protected $fillable = ['user_id', 'ip'];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_login',
            'comment' => '用户登录记录表',
            'columns' => [
                'id'         => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'    => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'ip'         => ['type' => 'string', 'length' => 45, 'default' => '', 'comment' => '登录IP'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id']],
                ['columns' => ['created_at']],
                ['columns' => ['ip']],
            ],
            'partition' => self::monthlyPartition('created_at', 'timestamp'),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
