<?php

declare(strict_types=1);

namespace app\model\invest;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class InvestmentProductSettlementTimeModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'iv_settlement_time';

    protected $fillable = [
        'settlement_time', 'status', 'sort',
    ];

    protected $casts = [
        'status' => 'integer',
        'sort' => 'integer',
    ];

    public const STATUS_DISABLE = 0;
    public const STATUS_ENABLE = 1;

    public static function tableSchema(): array
    {
        return [
            'table' => 'iv_settlement_time',
            'comment' => 'AI短周期托管结算时间点',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'settlement_time' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '结算时间点(H:i:s)'],
                'status' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '0禁用 1启用'],
                'sort' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '排序'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['status', 'settlement_time']],
            ],
        ];
    }
}
