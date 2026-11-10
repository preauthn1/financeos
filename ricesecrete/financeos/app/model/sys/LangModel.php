<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LangModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sy_lang';

    protected $fillable = [
        'title', 'lang', 'remark', 'is_default', 'is_default_lang',
        'flag', 'status', 'sort',
    ];

    protected $casts = [
        'is_default' => 'integer',
        'is_default_lang' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_lang',
            'comment' => '语言配置表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'title' => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '语言名称'],
                'lang' => ['type' => 'string', 'length' => 30, 'default' => '', 'comment' => '语言代码 zh-cn/pt'],
                'remark' => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '备注'],
                'is_default' => ['type' => 'unsignedTinyInteger', 'default' => 0, 'comment' => '默认地区 0否 1是'],
                'is_default_lang' => ['type' => 'unsignedTinyInteger', 'default' => 0, 'comment' => '默认语言 0否 1是'],
                'flag' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '国旗图标'],
                'status' => ['type' => 'unsignedTinyInteger', 'default' => 1, 'comment' => '状态 0禁用 1启用'],
                'sort' => ['type' => 'integer', 'default' => 0, 'comment' => '排序'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['lang'], 'unique' => true],
                ['columns' => ['status']],
            ],
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(LangItemDetailModel::class, 'lang_id');
    }
}
