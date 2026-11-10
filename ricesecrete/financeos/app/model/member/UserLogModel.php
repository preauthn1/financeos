<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use app\model\Concerns\PartitionedLogTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLogModel extends Model
{
    use DefinesTableSchema;
    use PartitionedLogTable;

    protected $table = 'me_user_log';

    protected $fillable = ['user_id', 'ip', 'area', 'type', 'device', 'uuid'];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_log',
            'comment' => '用户行为日志表',
            'columns' => [
                'id'         => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'    => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'ip'         => ['type' => 'string', 'length' => 45, 'default' => '', 'comment' => 'IP地址'],
                'area'       => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '地区'],
                'type'       => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '行为:0登录 1注册 2充值'],
                'device'     => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '设备类型'],
                'uuid'       => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '设备唯一ID'],
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
