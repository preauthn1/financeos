<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class TaskSummaryV2Model extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_task_summary_v2';

    protected $fillable = [
        'user_id', 'register', 'recharge', 'register_recharge', 'task_level1', 'task_level2', 'task_level3',
        'date', 'rec_settle', 'day_settle', 'week_settle', 'month_settle',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'me_task_summary_v2', 'comment' => '任务汇总V2表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'register' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '注册人数'],
                'recharge' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '有效充值人数'],
                'register_recharge' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '今日注册并充值'],
                'task_level1' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '一级完成任务数'],
                'task_level2' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '二级完成任务数'],
                'task_level3' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '三级完成任务数'],
                'date' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '日期YYYYMMDD'],
                'rec_settle' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '直推结算'],
                'day_settle' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '日结算'],
                'week_settle' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '周结算'],
                'month_settle' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '月结算'],
            ],
            'unique' => [['columns' => ['user_id', 'date']]],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['date']]],
        ];
    }
}
