<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use app\model\sys\AdminModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCreditScoreLogModel extends Model
{
    use DefinesTableSchema;

    public const TYPE_TRADE = 1;
    public const TYPE_ACTIVE = 2;
    public const TYPE_NEW = 3;
    public const TYPE_OVERDUE = 4;
    public const TYPE_ADMIN_ADD = 5;
    public const TYPE_ADMIN_SUB = 6;

    /** @var array<int, string> */
    public const TYPE_MAP = [
        self::TYPE_TRADE     => '交易量',
        self::TYPE_ACTIVE    => '活跃人数',
        self::TYPE_NEW       => '新增人数',
        self::TYPE_OVERDUE   => '逾期扣分',
        self::TYPE_ADMIN_ADD => '后台增加',
        self::TYPE_ADMIN_SUB => '后台减少',
    ];

    /** @var array<int, string> */
    public const TYPE_FIELD_MAP = [
        self::TYPE_TRADE  => 'trade_score',
        self::TYPE_ACTIVE => 'active_score',
        self::TYPE_NEW    => 'new_score',
    ];

    protected $table = 'me_user_credit_score_log';

    protected $fillable = [
        'user_id', 'from_user_id', 'score', 'before', 'after', 'type',
        'value', 'times', 'admin_id', 'order_id', 'title', 'remarks', 'overdue_at',
    ];

    protected $casts = [
        'user_id'      => 'integer',
        'from_user_id' => 'integer',
        'score'        => 'integer',
        'before'       => 'integer',
        'after'        => 'integer',
        'type'         => 'integer',
        'value'        => 'integer',
        'times'        => 'integer',
        'admin_id'     => 'integer',
        'order_id'     => 'integer',
        'overdue_at'   => 'datetime',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_credit_score_log',
            'comment' => '用户信用分变动日志',
            'columns' => [
                'id'           => ['type' => 'bigIncrements', 'comment' => '主键ID'],
                'user_id'      => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'from_user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '来源用户ID'],
                'score'        => ['type' => 'smallInteger', 'default' => 0, 'comment' => '变动分值'],
                'before'       => ['type' => 'smallInteger', 'default' => 0, 'comment' => '操作前信用分'],
                'after'        => ['type' => 'smallInteger', 'default' => 0, 'comment' => '操作后信用分'],
                'type'         => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '类型:1交易量 2活跃 3新增 4逾期 5后台加 6后台减'],
                'value'        => ['type' => 'integer', 'default' => 0, 'comment' => '加分条件值'],
                'times'        => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '次数'],
                'admin_id'     => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '管理员ID'],
                'order_id'     => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '订单ID'],
                'title'        => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '标题'],
                'remarks'      => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '备注'],
                'overdue_at'   => ['type' => 'timestamp', 'nullable' => true, 'comment' => '积分过期时间'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id']],
                ['columns' => ['type']],
                ['columns' => ['admin_id']],
                ['columns' => ['created_at']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminModel::class, 'admin_id');
    }
}
