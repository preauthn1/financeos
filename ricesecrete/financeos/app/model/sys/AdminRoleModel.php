<?php

declare(strict_types=1);

namespace app\model\sys;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminRoleModel extends Model
{
    use DefinesTableSchema;

    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'sy_admin_roles';

    public static function tableSchema(): array
    {
        return [
            'table' => 'sy_admin_roles',
            'comment' => '管理员角色关联表',
            'columns' => [
                'admin_id' => ['type' => 'unsignedInteger', 'comment' => '管理员ID'],
                'role_id' => ['type' => 'unsignedInteger', 'comment' => '角色ID'],
            ],
            'primary' => ['admin_id', 'role_id'],
            'indexes' => [
                ['columns' => ['role_id']],
            ],
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminModel::class, 'admin_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(RoleModel::class, 'role_id');
    }
}
