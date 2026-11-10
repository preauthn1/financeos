<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LangItemDetailModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sy_lang_item_detail';

    protected $fillable = ['lang_id', 'item_id', 'text'];

    protected $casts = [
        'lang_id' => 'integer',
        'item_id' => 'integer',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_lang_item_detail',
            'comment' => '多语言文案翻译表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'lang_id' => ['type' => 'unsignedInteger', 'comment' => '语言ID'],
                'item_id' => ['type' => 'unsignedInteger', 'comment' => '文案键ID'],
                'text' => ['type' => 'string', 'length' => 2000, 'default' => '', 'comment' => '翻译文本'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['lang_id', 'item_id'], 'unique' => true],
                ['columns' => ['item_id']],
            ],
        ];
    }

    public function lang(): BelongsTo
    {
        return $this->belongsTo(LangModel::class, 'lang_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LangItemModel::class, 'item_id');
    }
}
