<?php

declare(strict_types=1);

namespace app\model\content;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class CustomerServiceLinkModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'co_customer_services';

    protected $fillable = [
        'image_url', 'link', 'name', 'sort', 'status',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'co_customer_services',
            'comment' => '客服链接设置表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'image_url' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '图标'],
                'link' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '链接'],
                'name' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '名称'],
                'sort' => ['type' => 'unsignedTinyInteger', 'default' => 0, 'comment' => '排序(越小越靠前)'],
                'status' => ['type' => 'unsignedTinyInteger', 'default' => 1, 'comment' => '状态 0禁用 1启用'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['status']],
            ],
        ];
    }
}
