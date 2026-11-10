<?php

declare(strict_types=1);

namespace app\model\content;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class BannerModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'co_banners';

    protected $fillable = [
        'lang', 'name', 'link', 'image_url', 'status', 'sort', 'region',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'co_banners',
            'comment' => '轮播图表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'lang' => ['type' => 'string', 'length' => 30, 'default' => 'zh-cn', 'comment' => '语言'],
                'name' => ['type' => 'string', 'length' => 155, 'default' => '', 'comment' => '标题'],
                'link' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '跳转链接'],
                'image_url' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '图片地址'],
                'status' => ['type' => 'unsignedTinyInteger', 'default' => 1, 'comment' => '状态 0禁用 1启用'],
                'sort' => ['type' => 'integer', 'default' => 0, 'comment' => '排序(越小越靠前)'],
                'region' => ['type' => 'string', 'length' => 20, 'default' => 'index', 'comment' => '区域 index首页 introduce资产'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['status']],
                ['columns' => ['region']],
                ['columns' => ['lang']],
            ],
        ];
    }
}
