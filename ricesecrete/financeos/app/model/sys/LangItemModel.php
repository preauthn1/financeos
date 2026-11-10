<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LangItemModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sy_lang_items';

    protected $fillable = ['title'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_lang_items',
            'comment' => '多语言文案键表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'title' => ['type' => 'string', 'length' => 1000, 'default' => '', 'comment' => '文案键(通常为中文)'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(LangItemDetailModel::class, 'item_id');
    }
}
