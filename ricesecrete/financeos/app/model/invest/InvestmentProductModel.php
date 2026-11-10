<?php

declare(strict_types=1);

namespace app\model\invest;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class InvestmentProductModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'iv_product';

    protected $fillable = [
        'cycle', 'day_num', 'give_account_type', 'give_amount', 'description', 'income_text',
        'is_shortly_rebate', 'level_id', 'product_name', 'profit', 'started_at', 'ended_at',
        'profit_type',         'rebated_at', 'rebate_type', 'sales_status', 'min_balance', 'max_balance',
        'total_quota', 'order_limit',
        'commission_rates', 'sort', 'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'sales_status' => 'integer',
        'level_id' => 'integer',
        'commission_rates' => 'array',
        'order_limit' => 'integer',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'iv_product', 'comment' => 'AI托管产品配置表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'product_name' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '产品名称'],
                'cycle' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 1, 'comment' => '托管周期(天)'],
                'day_num' => ['type' => 'unsignedInteger', 'default' => 1, 'comment' => '每日次数'],
                'profit' => ['type' => 'decimal', 'precision' => 10, 'scale' => 4, 'default' => 0, 'comment' => '每日收益率'],
                'profit_type' => ['type' => 'tinyInteger', 'default' => 2, 'comment' => '收益类型'],
                'level_id' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '可购买VIP等级'],
                'give_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 2, 'default' => 0, 'comment' => '赠送金额'],
                'give_account_type' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '赠送账户类型'],
                'min_balance' => ['type' => 'decimal', 'precision' => 20, 'scale' => 2, 'default' => 0, 'comment' => '最低托管金额'],
                'max_balance' => ['type' => 'decimal', 'precision' => 20, 'scale' => 2, 'default' => 0, 'comment' => '最高托管金额(0不限)'],
                'total_quota' => ['type' => 'decimal', 'precision' => 20, 'scale' => 2, 'default' => 0, 'comment' => '共享托管总额度(0不限)'],
                'order_limit' => ['type' => 'unsignedInteger', 'default' => 5, 'comment' => '进行中托管次数限制'],
                'commission_rates' => ['type' => 'json', 'nullable' => true, 'comment' => '三级返佣比例JSON'],
                'started_at' => ['type' => 'string', 'length' => 8, 'default' => '00:00:00', 'comment' => '开始时间'],
                'ended_at' => ['type' => 'string', 'length' => 8, 'default' => '23:59:59', 'comment' => '结束时间'],
                'rebated_at' => ['type' => 'string', 'length' => 255, 'default' => '00:00', 'comment' => '派息时间'],
                'rebate_type' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '派息类型'],
                'is_shortly_rebate' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '是否立即派息'],
                'sales_status' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '销售状态'],
                'description' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '产品说明'],
                'income_text' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '收益说明'],
                'sort' => ['type' => 'integer', 'default' => 0, 'comment' => '排序'],
                'status' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '状态:0关闭 1开启'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['status']], ['columns' => ['sales_status']]],
        ];
    }
}
