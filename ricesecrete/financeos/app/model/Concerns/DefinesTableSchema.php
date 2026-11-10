<?php

declare(strict_types=1);

namespace app\model\Concerns;

/**
 * 在模型中声明表结构，供 `php webman model` 同步到数据库。
 *
 * @example
 * public static function tableSchema(): array
 * {
 *     return [
 *         'table' => 'articles',
 *         'comment' => '文章表',
 *         'columns' => [
 *             'id' => ['type' => 'increments', 'comment' => '主键ID'],
 *             'title' => ['type' => 'string', 'length' => 200, 'comment' => '文章标题'],
 *         ],
 *         'timestamps' => true,
 *         'columnComments' => [
 *             'created_at' => '创建时间',
 *             'updated_at' => '更新时间',
 *         ],
 *         'indexes' => [
 *             ['columns' => ['status']],
 *         ],
 *     ];
 * }
 */
trait DefinesTableSchema
{
    abstract public static function tableSchema(): array;
}
