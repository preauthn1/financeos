<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIpModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_user_ip';

    protected $fillable = ['ip', 'user_id'];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_ip',
            'comment' => '用户IP记录',
            'columns' => [
                'id'      => ['type' => 'increments', 'comment' => '主键ID'],
                'ip'      => ['type' => 'string', 'length' => 45, 'comment' => 'IP地址'],
                'user_id' => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['ip']],
                ['columns' => ['user_id']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}

