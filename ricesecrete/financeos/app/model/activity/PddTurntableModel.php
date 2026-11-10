<?php

declare(strict_types=1);

namespace app\model\activity;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class PddTurntableModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'act_pdd_turntable';

    protected $fillable = [
        'title', 'started_at', 'ended_at', 'status', 'config',
    ];

    protected $casts = ['config' => 'array'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'act_pdd_turntable', 'comment' => 'PDD抽奖活动表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'title' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '活动标题'],
                'started_at' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '开始时间(unix)'],
                'ended_at' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '结束时间(unix)'],
                'status' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '状态'],
                'config' => ['type' => 'json', 'nullable' => true, 'comment' => '活动配置'],
            ],
        ];
    }
}
