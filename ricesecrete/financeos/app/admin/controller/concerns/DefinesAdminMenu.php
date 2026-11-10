<?php

declare(strict_types=1);

namespace app\admin\controller\concerns;

/**
 * 在 Admin 控制器中声明菜单与 API 权限，供 `php webman menu` 扫描同步。
 * id 可省略，同步时按 slug/path 自动分配或复用数据库 ID。
 *
 * @example
 * public static function menuConfig(): ?array
 * {
 *     return [
 *         'group' => ['name' => '内容管理', 'slug' => 'content', 'path' => '/content', 'icon' => 'document', 'sort' => 10],
 *         'menu' => ['name' => '文章管理', 'slug' => 'article-menu', 'path' => '/content/article', 'icon' => 'edit', 'component' => 'views/article/index', 'sort' => 1],
 *         // 仅 API、无独立菜单页时，指定 API 挂载的父级菜单 slug
 *         'api_parent_menu_slug' => 'member-list',
 *         'apis' => [
 *             ['name' => '文章列表', 'slug' => 'article:list', 'path' => '/admin/articles', 'method' => 'GET', 'sort' => 1],
 *         ],
 *     ];
 * }
 */
trait DefinesAdminMenu
{
    public static function menuConfig(): ?array
    {
        return null;
    }
}
