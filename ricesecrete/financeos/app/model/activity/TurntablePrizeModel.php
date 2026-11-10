<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class TurntablePrizeModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_turntable_prize';
    public $timestamps = false;

    protected $fillable = [
        'turntable_id', 'level_id', 'class', 'name', 'num', 'type', 'value',
        'status', 'image_url', 'sort', 'probability',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_turntable_prize', 'comment' => '转盘奖品表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'turntable_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '转盘ID'],
                'level_id' => ['type' => 'integer', 'default' => -1, 'comment' => 'VIP等级,-1全局'],
                'class' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '1注册奖品 2邀请奖品'],
                'name' => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '奖品名称'],
                'num' => ['type' => 'integer', 'default' => -1, 'comment' => '数量,-1无限'],
                'type' => ['type' => 'integer', 'default' => 0, 'comment' => '奖品类型'],
                'value' => ['type' => 'string', 'length' => 50, 'default' => '0', 'comment' => '奖品值'],
                'status' => ['type' => 'integer', 'default' => 1, 'comment' => '状态'],
                'image_url' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '奖品图片'],
                'sort' => ['type' => 'integer', 'default' => 0, 'comment' => '排序'],
                'probability' => ['type' => 'decimal', 'precision' => 10, 'scale' => 4, 'default' => 1, 'comment' => '中奖概率'],
            ],
            'indexes' => [['columns' => ['turntable_id', 'status']]],
        ];
    }
}
