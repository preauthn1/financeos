<?php

declare(strict_types=1);

namespace app\model\invest;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class InvestmentSpeedCodeModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'iv_speed_code';

    protected $fillable = [
        'product_id', 'code', 'max_uses', 'used_count', 'status', 'remark',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'status' => 'integer',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'iv_speed_code',
            'comment' => 'AI托管加速编码',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'product_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '产品ID(0=全局)'],
                'code' => ['type' => 'string', 'length' => 20, 'default' => '', 'comment' => '加速编码(纯数字)'],
                'max_uses' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '最大使用次数(0不限)'],
                'used_count' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '已使用次数'],
                'status' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '状态:0关闭 1开启'],
                'remark' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '备注'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['product_id']], ['columns' => ['code']]],
        ];
    }
}
