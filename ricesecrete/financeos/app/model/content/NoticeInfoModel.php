<?php

declare(strict_types=1);

namespace app\model\content;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeInfoModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'co_notice_info';

    protected $fillable = [
        'lang', 'notice_id', 'title', 'content',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'co_notice_info',
            'comment' => '公告多语言内容表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'lang' => ['type' => 'string', 'length' => 30, 'default' => '', 'comment' => '语种'],
                'notice_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '公告ID'],
                'title' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '标题'],
                'content' => ['type' => 'text', 'nullable' => true, 'comment' => '内容'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['notice_id']],
                ['columns' => ['lang']],
            ],
            'unique' => [
                ['columns' => ['notice_id', 'lang']],
            ],
        ];
    }

    public function notice(): BelongsTo
    {
        return $this->belongsTo(NoticeModel::class, 'notice_id');
    }
}
