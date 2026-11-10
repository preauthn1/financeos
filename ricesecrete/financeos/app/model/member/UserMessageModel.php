<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMessageModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_user_message';

    protected $fillable = [
        'user_id', 'admin_id', 'type', 'title', 'content',
        'is_read', 'read_at', 'is_popup',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_message',
            'comment' => '站内信',
            'columns' => [
                'id'       => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'  => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'admin_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '发送管理员ID(0=系统)'],
                'type'     => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '消息类型:1自定义 2充值到账 3提现到账'],
                'title'    => ['type' => 'string', 'length' => 200, 'default' => '', 'comment' => '标题'],
                'content'  => ['type' => 'string', 'length' => 2000, 'default' => '', 'comment' => '内容'],
                'is_read'  => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是否已读:0否 1是'],
                'read_at'  => ['type' => 'unsignedBigInteger', 'nullable' => true, 'comment' => '阅读时间(unix)'],
                'is_popup'   => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是否弹窗:0否 1是'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id', 'is_read', 'is_popup']],
                ['columns' => ['type']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
