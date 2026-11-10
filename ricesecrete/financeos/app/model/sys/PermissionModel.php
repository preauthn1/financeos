<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sy_permissions';

    protected $fillable = [
        'name', 'slug', 'path', 'method', 'parent_id',
        'type', 'icon', 'component', 'sort', 'hidden',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_permissions',
            'comment' => '权限表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'name' => ['type' => 'string', 'length' => 50, 'comment' => '权限名称'],
                'slug' => ['type' => 'string', 'length' => 100, 'comment' => '权限标识'],
                'path' => ['type' => 'string', 'length' => 200, 'default' => '', 'comment' => '路由或API路径'],
                'method' => ['type' => 'string', 'length' => 10, 'default' => '', 'comment' => 'HTTP方法'],
                'parent_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '上级权限ID'],
                'type' => ['type' => 'enum', 'values' => ['menu', 'button', 'api'], 'default' => 'api', 'comment' => '权限类型'],
                'icon' => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '菜单图标'],
                'component' => ['type' => 'string', 'length' => 200, 'default' => '', 'comment' => '前端组件路径'],
                'sort' => ['type' => 'integer', 'default' => 0, 'comment' => '排序值'],
                'hidden' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是否隐藏：0否 1是'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'unique' => [
                ['columns' => ['slug']],
            ],
            'indexes' => [
                ['columns' => ['parent_id']],
                ['columns' => ['type']],
            ],
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RoleModel::class, 'sy_role_permissions', 'permission_id', 'role_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }
}
