<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class ActivityPasswordRecordModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_activity_password_record';

    protected $fillable = [
        'is_virtual', 'activity_id', 'user_id', 'password',
    ];

    protected $casts = [
        'is_virtual' => 'integer',
        'activity_id' => 'integer',
        'user_id' => 'integer',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_activity_password_record',
            'comment' => '活动编码参与记录',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'is_virtual' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '虚拟用户'],
                'activity_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '活动ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'password' => ['type' => 'string', 'length' => 20, 'default' => '', 'comment' => '输入的编码'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id']],
                ['columns' => ['activity_id']],
            ],
        ];
    }
}
