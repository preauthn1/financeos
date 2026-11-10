<?php

declare(strict_types=1);

namespace app\model\ad;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class AdPlanUserModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'ad_plan_user';

    protected $fillable = ['user_id', 'ad_plan_id', 'previous_plan_id'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'ad_plan_user',
            'comment' => '用户广告计划参与记录',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'ad_plan_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '计划ID'],
                'previous_plan_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '上一计划ID'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['user_id', 'ad_plan_id']]],
        ];
    }
}
