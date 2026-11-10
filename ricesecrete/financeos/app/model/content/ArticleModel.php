<?php

declare(strict_types=1);

namespace app\model\content;

use app\model\Concerns\DefinesTableSchema;
use app\model\sys\AdminModel;
use app\support\ArticleCategoryDict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleModel extends Model
{
    use DefinesTableSchema;
    use SoftDeletes;

    // 内容模块表前缀
    protected $table = 'co_articles';

    protected $fillable = [
        'title', 'summary', 'content', 'category_id', 'thumb', 'lang', 'sort',
        'status', 'author_id', 'view_count',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'co_articles',
            'comment' => '文章表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'title' => ['type' => 'string', 'length' => 200, 'comment' => '文章标题'],
                'summary' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '文章摘要'],
                'content' => ['type' => 'longText', 'nullable' => true, 'comment' => '文章内容'],
                'category_id' => ['type' => 'unsignedInteger', 'nullable' => true, 'comment' => '文章分类(字典 article_category 的 value)'],
                'thumb' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '封面图'],
                'lang' => ['type' => 'string', 'length' => 20, 'default' => '', 'comment' => '语言标识'],
                'sort' => ['type' => 'integer', 'default' => 0, 'comment' => '排序'],
                'status' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '状态：0草稿 1已发布'],
                'author_id' => ['type' => 'unsignedInteger', 'comment' => '作者管理员ID'],
                'view_count' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '阅读量'],
                'deleted_at' => ['type' => 'timestamp', 'nullable' => true, 'comment' => '软删除时间'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['status']],
                ['columns' => ['category_id']],
                ['columns' => ['lang']],
                ['columns' => ['author_id']],
                ['columns' => ['created_at']],
                ['columns' => ['deleted_at']],
            ],
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(AdminModel::class, 'author_id');
    }

    public static function categoryDictCode(): string
    {
        return ArticleCategoryDict::CODE;
    }
}
