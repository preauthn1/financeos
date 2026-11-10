<?php

declare(strict_types=1);

namespace app\model\service;

use app\model\Concerns\DefinesTableSchema;
use app\model\member\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCenterApplicationModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'sc_service_center_application';

    protected $fillable = [
        'service_center_id', 'user_id', 'auth_code', 'auth_id', 'media', 'status', 'remark', 'audited_at',
    ];

    protected $casts = [
        'service_center_id' => 'integer',
        'user_id' => 'integer',
        'media' => 'array',
        'status' => 'integer',
        'audited_at' => 'integer',
    ];

    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    public static function tableSchema(): array
    {
        return [
            'table' => 'sc_service_center_application',
            'comment' => '服务中心工作室申请',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'service_center_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '服务中心ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'auth_code' => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '认证编码'],
                'auth_id' => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '认证ID'],
                'media' => ['type' => 'json', 'nullable' => true, 'comment' => '图片/视频'],
                'status' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '0待审 1通过 2拒绝'],
                'remark' => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '审核备注'],
                'audited_at' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '审核时间'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id']],
                ['columns' => ['service_center_id']],
                ['columns' => ['status']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function serviceCenter(): BelongsTo
    {
        return $this->belongsTo(ServiceCenterModel::class, 'service_center_id');
    }
}
