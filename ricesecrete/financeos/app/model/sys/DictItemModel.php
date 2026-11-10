<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DictItemModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sy_dict_items';

    protected $fillable = ['dict_type_id', 'label', 'value', 'sort', 'status', 'remark'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_dict_items',
            'comment' => '字典项表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'dict_type_id' => ['type' => 'unsignedInteger', 'comment' => '字典类型ID'],
                'label' => ['type' => 'string', 'length' => 100, 'comment' => '显示标签'],
                'value' => ['type' => 'string', 'length' => 100, 'comment' => '字典值'],
                'sort' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '排序'],
                'status' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '状态：0禁用 1启用'],
                'remark' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '备注'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['dict_type_id']],
                ['columns' => ['value']],
            ],
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DictTypeModel::class, 'dict_type_id');
    }
}
