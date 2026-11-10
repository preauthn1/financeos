<?php

declare(strict_types=1);

/**
 * 权限由控制器 menuConfig 定义，php webman menu 同步到数据库。
 * ID 由同步器按 slug/path 自动分配或复用，无需在代码中写死。
 */
return [
    /** 编辑员角色默认分配的 API 权限 slug */
    'editor_api_slugs' => [
        'article:list',
        'article:meta',
        'article:create',
        'article:update',
        'article:delete',
    ],
];
