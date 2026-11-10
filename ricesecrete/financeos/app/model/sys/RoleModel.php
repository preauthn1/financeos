<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RoleModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sy_roles';

    protected $fillable = ['name', 'slug', 'description', 'data_scope'];

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_roles',
            'comment' => '角色表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'name' => ['type' => 'string', 'length' => 50, 'comment' => '角色名称'],
                'slug' => ['type' => 'string', 'length' => 50, 'comment' => '角色标识'],
                'description' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '角色描述'],
                'data_scope' => ['type' => 'enum', 'values' => ['all', 'self'], 'default' => 'self', 'comment' => '数据权限：all全部 self仅本人'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'unique' => [
                ['columns' => ['slug']],
            ],
        ];
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(AdminModel::class, 'sy_admin_roles', 'role_id', 'admin_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(PermissionModel::class, 'sy_role_permissions', 'role_id', 'permission_id');
    }
}
