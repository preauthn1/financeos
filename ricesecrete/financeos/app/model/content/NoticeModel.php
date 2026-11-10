<?php

declare(strict_types=1);

namespace app\model\content;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoticeModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'co_notices';

    protected $fillable = [
        'user_id', 'lang', 'title', 'type', 'content', 'status',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'co_notices',
            'comment' => '系统公告表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '指定用户ID，0为全部用户'],
                'lang' => ['type' => 'string', 'length' => 30, 'default' => 'zh-cn', 'comment' => '默认语言'],
                'title' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '标题'],
                'type' => ['type' => 'unsignedTinyInteger', 'default' => 0, 'comment' => '0系统公告 1指定用户公告'],
                'content' => ['type' => 'text', 'nullable' => true, 'comment' => '公告内容'],
                'status' => ['type' => 'unsignedTinyInteger', 'default' => 1, 'comment' => '状态 0禁用 1启用'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['status']],
                ['columns' => ['user_id']],
            ],
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(NoticeInfoModel::class, 'notice_id');
    }
}
