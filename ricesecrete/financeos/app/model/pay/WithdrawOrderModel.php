<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use app\model\member\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawOrderModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'pa_withdraw_order';

    protected $fillable = [
        'order_no', 'user_id', 'is_virtual', 'withdraw_amount', 'original_amount',
        'exchange_rate', 'payout_amount', 'handling_fee', 'tx_fee',
        'status', 'true_name', 'bank_name', 'bank_code', 'bank_number',
        'bank_branch', 'bank_address', 'document_type', 'document_id',
        'type', 'trade_no', 'paid_at', 'checked_at', 'channel', 'ip',
        'withdrawal_address', 'from_address', 'remark', 'admin_id', 'coin',
        'locked_by', 'locked_at', 'audit_admin_id', 'payout_admin_id', 'card_reason',
    ];

    protected $casts = [
        'is_virtual' => 'integer',
        'status' => 'integer',
        'type' => 'integer',
        'admin_id' => 'integer',
        'checked_at' => 'integer',
        'locked_by' => 'integer',
        'locked_at' => 'integer',
        'audit_admin_id' => 'integer',
        'payout_admin_id' => 'integer',
    ];

    public const STATUS_PENDING = 0;
    public const STATUS_WAIT = 1;
    public const STATUS_AGREE = 2;
    public const STATUS_REJECT = 3;
    public const STATUS_SUCCESS = 4;
    public const STATUS_FAIL = 5;
    public const STATUS_OFFLINE_FAIL = 6;
    public const STATUS_CARD_REVIEW = 7;
    public const STATUS_PAYING = 8;

    public const TYPE_CRYPTO = 1;
    public const TYPE_ALIPAY = 2;
    public const TYPE_BANK = 3;

    public static array $statusMap = [
        self::STATUS_PENDING      => '待处理',
        self::STATUS_WAIT         => '待审核',
        self::STATUS_AGREE        => '待出款',
        self::STATUS_REJECT       => '审核失败',
        self::STATUS_SUCCESS      => '已出款',
        self::STATUS_FAIL         => '出款失败',
        self::STATUS_OFFLINE_FAIL => '线下出款失败',
        self::STATUS_CARD_REVIEW  => '卡审核',
        self::STATUS_PAYING       => '出款中',
    ];

    public static array $typeMap = [
        self::TYPE_CRYPTO => '虚拟币',
        self::TYPE_ALIPAY => '支付宝',
        self::TYPE_BANK   => '银行卡',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'pa_withdraw_order',
            'comment' => '提现订单表',
            'columns' => [
                'id'               => ['type' => 'bigIncrements', 'comment' => '主键ID'],
                'order_no'         => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '订单号'],
                'user_id'          => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'is_virtual'       => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是否虚拟用户'],
                'withdraw_amount'   => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '实际到账金额'],
                'original_amount'   => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '提现金额'],
                'exchange_rate'    => ['type' => 'decimal', 'precision' => 20, 'scale' => 8, 'default' => 1, 'comment' => '汇率'],
                'payout_amount'    => ['type' => 'decimal', 'precision' => 10, 'scale' => 4, 'default' => 0, 'comment' => '出款金额'],
                'handling_fee'     => ['type' => 'decimal', 'precision' => 10, 'scale' => 4, 'nullable' => true, 'comment' => '提现手续费'],
                'tx_fee'           => ['type' => 'decimal', 'precision' => 10, 'scale' => 4, 'nullable' => true, 'comment' => '转账手续费'],
                'status'           => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '状态'],
                'true_name'        => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '真实姓名'],
                'bank_name'        => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '银行名称'],
                'bank_code'        => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '银行代码'],
                'bank_number'      => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '银行账号'],
                'bank_branch'      => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '开户行'],
                'bank_address'     => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '支行地址'],
                'document_type'    => ['type' => 'string', 'length' => 20, 'nullable' => true, 'comment' => '账户类型'],
                'document_id'      => ['type' => 'string', 'length' => 100, 'nullable' => true, 'comment' => '账户账号'],
                'type'             => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '类型:1虚拟币 2支付宝 3银行卡'],
                'trade_no'         => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '第三方订单号'],
                'paid_at'         => ['type' => 'unsignedBigInteger', 'nullable' => true, 'comment' => '付款时间(unix)'],
                'checked_at'       => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '审核时间(unix)'],
                'channel'          => ['type' => 'string', 'length' => 50, 'nullable' => true, 'comment' => '代付渠道'],
                'ip'               => ['type' => 'string', 'length' => 45, 'default' => '', 'comment' => '操作IP'],
                'withdrawal_address' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '提现地址'],
                'from_address'     => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '出款地址'],
                'remark'           => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '备注'],
                'admin_id'         => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '操作管理员ID'],
                'coin'             => ['type' => 'string', 'length' => 50, 'nullable' => true, 'comment' => '虚拟币类型'],
                'locked_by'        => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '锁单管理员ID'],
                'locked_at'        => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '锁单时间(unix)'],
                'audit_admin_id'   => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '审核管理员ID'],
                'payout_admin_id'  => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '出款管理员ID'],
                'card_reason'      => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '卡审核原因'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['status']],
                ['columns' => ['user_id']],
                ['columns' => ['order_no']],
                ['columns' => ['type']],
                ['columns' => ['locked_by']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
