<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DictTypeModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sy_dict_types';

    protected $fillable = ['name', 'code', 'status', 'remark'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_dict_types',
            'comment' => '字典类型表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'name' => ['type' => 'string', 'length' => 100, 'comment' => '字典名称'],
                'code' => ['type' => 'string', 'length' => 100, 'comment' => '字典编码'],
                'status' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '状态：0禁用 1启用'],
                'remark' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '备注'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'unique' => [
                ['columns' => ['code']],
            ],
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DictItemModel::class, 'dict_type_id');
    }
}
