<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayPlatformModel extends Model
{
    use DefinesTableSchema;
    use SoftDeletes;

    protected $table = 'pa_platform';

    protected $fillable = [
        'currency_type', 'currency_code', 'unit', 'name', 'show_name', 'image_url',
        'min_recharge_amount', 'min_withdraw_amount', 'max_withdraw_amount', 'fast_amount',
        'recharge_is_show', 'withdraw_is_show', 'withdraw_money_type',
        'config', 'status', 'is_first_charge', 'sort',
        'exchange_rate', 'exchange_updated_at', 'instruction', 'remark', 'coin',
    ];

    protected $casts = [
        'config' => 'array',
        'recharge_is_show' => 'integer',
        'withdraw_is_show' => 'integer',
        'status' => 'integer',
        'is_first_charge' => 'integer',
        'sort' => 'integer',
        'currency_type' => 'integer',
        'withdraw_money_type' => 'integer',
    ];

    public const CURRENCY_CRYPTO = 1;
    public const CURRENCY_FIAT = 2;
    public const CURRENCY_BANK = 3;

    public static function tableSchema(): array
    {
        return [
            'table'   => 'pa_platform',
            'comment' => '支付通道配置表',
            'columns' => [
                'id'                 => ['type' => 'increments', 'comment' => '主键ID'],
                'currency_type'      => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 2, 'comment' => '货币类型:1虚拟币 2法币 3银行卡'],
                'currency_code'      => ['type' => 'string', 'length' => 20, 'default' => '', 'comment' => '通道类型编码'],
                'unit'               => ['type' => 'string', 'length' => 20, 'default' => '', 'comment' => '货币单位'],
                'name'               => ['type' => 'string', 'length' => 255, 'comment' => '渠道名称'],
                'show_name'          => ['type' => 'string', 'length' => 255, 'nullable' => true, 'comment' => '前端显示名称'],
                'image_url'              => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '图标'],
                'min_recharge_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 8, 'default' => 0, 'comment' => '最小充值金额'],
                'min_withdraw_amount' => ['type' => 'decimal', 'precision' => 10, 'scale' => 4, 'default' => 0, 'comment' => '最小提现金额'],
                'max_withdraw_amount' => ['type' => 'decimal', 'precision' => 10, 'scale' => 4, 'default' => 0, 'comment' => '最大提现金额'],
                'fast_amount'        => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '快捷金额(逗号分隔)'],
                'recharge_is_show'   => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '充值开关'],
                'withdraw_is_show'   => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '提现开关'],
                'withdraw_money_type'=> ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '提现金额类型:1自由输入 2固定金额'],
                'config'             => ['type' => 'json', 'nullable' => true, 'comment' => '通道配置JSON'],
                'status'             => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '状态:0禁用 1启用'],
                'is_first_charge'    => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是否首充渠道'],
                'sort'               => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '排序'],
                'exchange_rate'      => ['type' => 'decimal', 'precision' => 20, 'scale' => 6, 'default' => 1, 'comment' => '汇率'],
                'exchange_updated_at' => ['type' => 'timestamp', 'nullable' => true, 'comment' => '汇率更新时间'],
                'instruction'        => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '充值说明'],
                'remark'             => ['type' => 'string', 'length' => 200, 'nullable' => true, 'comment' => '备注'],
                'coin'               => ['type' => 'string', 'length' => 50, 'nullable' => true, 'comment' => '虚拟币类型'],
                'deleted_at'         => ['type' => 'timestamp', 'nullable' => true, 'comment' => '软删除时间'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['status']],
                ['columns' => ['currency_type']],
                ['columns' => ['recharge_is_show']],
                ['columns' => ['withdraw_is_show']],
            ],
        ];
    }
}
