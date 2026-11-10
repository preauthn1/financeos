<?php

declare(strict_types=1);

namespace app\model\service;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class ServiceCenterModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sc_service_center';

    protected $fillable = [
        'name', 'thumb', 'address', 'images', 'content', 'sort', 'status',
    ];

    protected $casts = [
        'images' => 'array',
        'sort' => 'integer',
        'status' => 'integer',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sc_service_center',
            'comment' => '服务中心',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'name' => ['type' => 'string', 'length' => 200, 'default' => '', 'comment' => '名称'],
                'thumb' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '缩略图'],
                'address' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '地址名称'],
                'images' => ['type' => 'json', 'nullable' => true, 'comment' => '宣传图片列表'],
                'content' => ['type' => 'text', 'nullable' => true, 'comment' => '详情介绍'],
                'sort' => ['type' => 'integer', 'default' => 0, 'comment' => '排序'],
                'status' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '状态:0关闭 1开启'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['status']]],
        ];
    }
}
