<?php

declare(strict_types=1);

namespace app\model\invest;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class InvestmentProductSessionModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'iv_product_session';

    protected $fillable = [
        'start_time', 'end_time', 'status', 'sort',
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
            'table' => 'iv_product_session',
            'comment' => 'AI智能托管开放时间段',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'start_time' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '场次开始时间(H:i:s)'],
                'end_time' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '场次结束时间(H:i:s)'],
                'status' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '0禁用 1启用'],
                'sort' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '排序'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['status', 'start_time', 'end_time']],
            ],
        ];
    }
}
