<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class SettingModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sy_settings';

    protected $fillable = ['key', 'value', 'description'];

    protected $casts = [
        'value' => 'array',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_settings',
            'comment' => '系统配置表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'key' => ['type' => 'string', 'length' => 100, 'comment' => '配置键名'],
                'value' => ['type' => 'json', 'comment' => '配置值（JSON）'],
                'description' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '配置说明'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'unique' => [
                ['columns' => ['key']],
            ],
        ];
    }
}
