<?php

declare(strict_types=1);

namespace app\model\ad;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class AdPlanDaysConfigModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'ad_plan_days_config';

    public $timestamps = false;

    protected $fillable = [
        'ad_plan_id', 'day', 'task_session_id', 'level_id',
        'invest_rate', 'invest_profit', 'invest_day', 'invest_period_type',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'ad_plan_days_config',
            'comment' => '广告计划天数配置',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'ad_plan_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '计划ID'],
                'day' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '第几天'],
                'task_session_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '时段ID'],
                'level_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '等级ID'],
                'invest_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '占余额比例'],
                'invest_profit' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '收益率'],
                'invest_day' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '周期'],
                'invest_period_type' => ['type' => 'string', 'length' => 10, 'default' => 'day', 'comment' => '周期类型'],
            ],
            'indexes' => [['columns' => ['ad_plan_id', 'day', 'task_session_id', 'level_id'], 'name' => 'idx_ad_plan_day_session']],
        ];
    }
}
