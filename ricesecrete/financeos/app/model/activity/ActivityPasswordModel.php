<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class ActivityPasswordModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_activity_password';

    protected $fillable = [
        'activity_id', 'password', 'password_updated_at', 'num', 'status', 'remark',
    ];

    protected $casts = [
        'activity_id' => 'integer',
        'status' => 'integer',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_activity_password',
            'comment' => '活动解锁编码批次',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'activity_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '活动ID'],
                'password' => ['type' => 'string', 'length' => 20, 'default' => '', 'comment' => '解锁编码'],
                'password_updated_at' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '编码更新时间'],
                'num' => ['type' => 'unsignedInteger', 'default' => 1, 'comment' => '可用次数'],
                'status' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '状态:0关闭 1开启'],
                'remark' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '备注'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['activity_id']], ['columns' => ['password']]],
        ];
    }
}
