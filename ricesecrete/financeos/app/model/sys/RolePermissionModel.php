<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermissionModel extends Model
{
    use DefinesTableSchema;

    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'sy_role_permissions';

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_role_permissions',
            'comment' => '角色权限关联表',
            'columns' => [
                'role_id' => ['type' => 'unsignedInteger', 'comment' => '角色ID'],
                'permission_id' => ['type' => 'unsignedInteger', 'comment' => '权限ID'],
            ],
            'primary' => ['role_id', 'permission_id'],
            'indexes' => [
                ['columns' => ['permission_id']],
            ],
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(RoleModel::class, 'role_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(PermissionModel::class, 'permission_id');
    }
}
