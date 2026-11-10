<?php

declare(strict_types=1);

namespace app\model\ad;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class AdAdvertisNumModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'ad_advertis_num';

    protected $fillable = ['advertis_name', 'num'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'ad_advertis_num', 'comment' => '广告库存数量表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'advertis_name' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '库存名称'],
                'num' => ['type' => 'decimal', 'precision' => 10, 'scale' => 4, 'nullable' => true, 'comment' => '库存数量'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['advertis_name']]],
        ];
    }
}
