<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use app\model\Concerns\PartitionedLogTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLogModel extends Model
{
    use DefinesTableSchema;
    use PartitionedLogTable;

    protected $table = 'sy_admin_logs';

    protected $fillable = [
        'admin_id', 'module', 'action', 'description',
        'request_data', 'ip', 'user_agent',
    ];

    protected $casts = [
        'request_data' => 'array',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_admin_logs',
            'comment' => '操作日志表',
            'columns' => [
                'id' => ['type' => 'bigIncrements', 'comment' => '主键ID'],
                'admin_id' => ['type' => 'unsignedInteger', 'comment' => '操作管理员ID'],
                'module' => ['type' => 'string', 'length' => 50, 'comment' => '操作模块'],
                'action' => ['type' => 'string', 'length' => 50, 'comment' => '操作动作'],
                'description' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '操作描述'],
                'request_data' => ['type' => 'json', 'nullable' => true, 'comment' => '请求数据（JSON）'],
                'ip' => ['type' => 'string', 'length' => 45, 'default' => '', 'comment' => '操作IP'],
                'user_agent' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => 'User-Agent'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '操作时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['admin_id']],
                ['columns' => ['module']],
                ['columns' => ['created_at']],
            ],
            'partition' => self::monthlyPartition('created_at', 'timestamp'),
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminModel::class, 'admin_id');
    }
}
