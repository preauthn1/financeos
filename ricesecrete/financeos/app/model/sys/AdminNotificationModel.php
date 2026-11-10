<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotificationModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sy_admin_notifications';

    protected $fillable = [
        'admin_id', 'title', 'content', 'type', 'is_read', 'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_admin_notifications',
            'comment' => '站内通知表',
            'columns' => [
                'id' => ['type' => 'bigIncrements', 'comment' => '主键ID'],
                'admin_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '接收管理员ID，0表示全体'],
                'title' => ['type' => 'string', 'length' => 200, 'comment' => '通知标题'],
                'content' => ['type' => 'text', 'comment' => '通知内容'],
                'type' => ['type' => 'enum', 'values' => ['notice', 'announcement', 'system'], 'default' => 'notice', 'comment' => '通知类型'],
                'is_read' => ['type' => 'boolean', 'default' => false, 'comment' => '是否已读'],
                'read_at' => ['type' => 'timestamp', 'nullable' => true, 'comment' => '阅读时间'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['admin_id']],
                ['columns' => ['is_read']],
                ['columns' => ['type']],
            ],
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminModel::class, 'admin_id');
    }
}
