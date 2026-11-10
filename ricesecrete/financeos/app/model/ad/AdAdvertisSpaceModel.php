<?php

declare(strict_types=1);

namespace app\model\ad;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class AdAdvertisSpaceModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'ad_advertis_space';

    protected $fillable = [
        'advertis_name', 'exposure', 'turnover', 'turnover_num', 'turnover_amount',
        'cpm_cost_amount', 'advertis_en_name',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'ad_advertis_space', 'comment' => '广告库存信息表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'advertis_name' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '库存名称'],
                'advertis_en_name' => ['type' => 'string', 'length' => 255, 'nullable' => true, 'comment' => '英文名称'],
                'exposure' => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'nullable' => true, 'comment' => '曝光量'],
                'turnover' => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'nullable' => true, 'comment' => '成交量'],
                'turnover_num' => ['type' => 'integer', 'nullable' => true, 'comment' => '成交笔数'],
                'turnover_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 2, 'default' => 0, 'comment' => '成交金额'],
                'cpm_cost_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 2, 'default' => 0, 'comment' => 'CPM成本'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['advertis_en_name']], ['columns' => ['created_at']]],
        ];
    }
}
