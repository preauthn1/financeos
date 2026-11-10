<?php

declare(strict_types=1);

namespace app\model\content;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class VideoModel extends Model
{
    use DefinesTableSchema;

    public const CATE_AGENT = 1;
    public const CATE_PUBLISHER = 2;
    public const CATE_MERCHANT = 3;
    public const CATE_AD = 4;
    public const CATE_INTRODUCTION = 5;
    public const CATE_INDEX = 6;

    /** 后台可选分类（与参考项目一致，已移除 1/2/3） */
    public const ADMIN_CATE_MAP = [
        self::CATE_INDEX => '首页',
        self::CATE_AD => '广告',
        self::CATE_INTRODUCTION => '介绍',
    ];

    /** @var array<int, string> 全部分类（含历史数据） */
    public const CATE_MAP = [
        self::CATE_AGENT => '广告代理商',
        self::CATE_PUBLISHER => '广告发行商',
        self::CATE_MERCHANT => '广告主',
        self::CATE_AD => '广告',
        self::CATE_INTRODUCTION => '介绍',
        self::CATE_INDEX => '首页',
    ];

    public static function cateLabel(int $cate): string
    {
        return self::CATE_MAP[$cate] ?? (string) $cate;
    }

    protected $table = 'co_video';

    protected $fillable = [
        'lang', 'cate', 'level_id', 'sub_name', 'name', 'image_url', 'video_url', 'duration',
        'director', 'description', 'watch_times', 'sort', 'status', 'remark',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'co_video', 'comment' => '视频任务表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'lang' => ['type' => 'string', 'length' => 30, 'default' => 'zh-cn', 'comment' => '语言'],
                'cate' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '分类ID'],
                'level_id' => ['type' => 'integer', 'default' => 0, 'comment' => '关联等级'],
                'sub_name' => ['type' => 'string', 'length' => 255, 'nullable' => true, 'comment' => '副标题'],
                'name' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '视频名称'],
                'image_url' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '封面图'],
                'video_url' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '视频链接'],
                'duration' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '时长(秒)'],
                'director' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '导演'],
                'description' => ['type' => 'text', 'comment' => '视频详情'],
                'watch_times' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '观看次数'],
                'sort' => ['type' => 'unsignedInteger', 'default' => 50, 'comment' => '排序'],
                'status' => ['type' => 'unsignedInteger', 'default' => 1, 'comment' => '状态'],
                'remark' => ['type' => 'string', 'length' => 255, 'nullable' => true, 'comment' => '备注'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['status']], ['columns' => ['cate']], ['columns' => ['level_id']]],
        ];
    }
}
