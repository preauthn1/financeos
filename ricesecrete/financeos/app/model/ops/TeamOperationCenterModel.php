<?php

declare(strict_types=1);

namespace app\model\ops;

use app\model\Concerns\DefinesTableSchema;
use app\model\member\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamOperationCenterModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'toc_operation_center';

    protected $fillable = [
        'user_id', 'submit_user_id', 'type', 'auth_code', 'auth_data', 'status',
        'dividend', 'rebate', 'fund', 'bonus', 'sub_count', 'authenticated_at',
    ];

    public const TYPE_PROXY = 1;
    public const TYPE_GROUP = 2;
    public const TYPE_CITY = 3;

    public const STATUS_PENDING = 1;
    public const STATUS_APPROVED = 2;
    public const STATUS_REJECTED = 3;
    public const STATUS_INVALID = 4;

    public static array $typeMap = [
        self::TYPE_PROXY => '代理运营中心',
        self::TYPE_GROUP => '团队运营中心',
        self::TYPE_CITY  => '城市运营中心',
    ];

    public static array $statusMap = [
        self::STATUS_PENDING  => '待认证',
        self::STATUS_APPROVED => '已认证',
        self::STATUS_REJECTED => '认证失败',
        self::STATUS_INVALID  => '资格已失效',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'toc_operation_center', 'comment' => '运营中心申请表',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'submit_user_id' => ['type' => 'unsignedBigInteger', 'nullable' => true, 'comment' => '提交人ID'],
                'type' => ['type' => 'integer', 'comment' => '类型:1代理 2团队 3城市'],
                'auth_code' => ['type' => 'string', 'length' => 50, 'nullable' => true, 'comment' => '认证编码'],
                'auth_data' => ['type' => 'string', 'length' => 2000, 'nullable' => true, 'comment' => '认证资料'],
                'status' => ['type' => 'integer', 'default' => 1, 'comment' => '状态:1待认证 2已认证 3失败 4失效'],
                'dividend' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'nullable' => true, 'comment' => '团队交易日分红'],
                'rebate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'nullable' => true, 'comment' => '入金返佣'],
                'fund' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'nullable' => true, 'comment' => '发展基金'],
                'bonus' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'nullable' => true, 'comment' => '待领取奖金'],
                'sub_count' => ['type' => 'integer', 'nullable' => true, 'comment' => '下级运营中心数'],
                'authenticated_at' => ['type' => 'unsignedBigInteger', 'nullable' => true, 'comment' => '审核通过时间(unix)'],
            ],
            'unique' => [['columns' => ['user_id', 'type']]],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [['columns' => ['status']], ['columns' => ['submit_user_id']]],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function submitUser(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'submit_user_id');
    }
}
