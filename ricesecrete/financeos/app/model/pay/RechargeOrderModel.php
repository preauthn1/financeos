<?php

declare(strict_types=1);

namespace app\model\pay;

use app\model\Concerns\DefinesTableSchema;
use app\model\member\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RechargeOrderModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'pa_recharge_order';

    protected $fillable = [
        'order_no', 'user_id', 'is_virtual', 'money', 'original_amount', 'exchange_rate',
        'type', 'status', 'pay_platform_id', 'trade_no', 'transaction_hash', 'chain_tx_id', 'voucher_url',
        'remark', 'user_note', 'paid_at', 'succeeded_at', 'admin_id',
        'order_source', 'ip', 'is_first', 'coin', 'coin_amount', 'result',
        'to_address', 'from_address', 'pay_account',
    ];

    protected $casts = [
        'is_virtual' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
        'admin_id' => 'integer',
        'order_source' => 'integer',
        'is_first' => 'integer',
        'pay_platform_id' => 'integer',
    ];

    public const TYPE_MANUAL = 0;
    public const TYPE_OFFLINE = 1;
    public const TYPE_CONTRACT = 2;
    public const TYPE_THIRD = 3;

    public const STATUS_PENDING = 0;
    public const STATUS_WAIT = 1;
    public const STATUS_SUCCESS = 2;
    public const STATUS_FAIL = 3;

    public const FROM_FRONT = 1;
    public const FROM_ADMIN = 2;

    public static array $statusMap = [
        self::STATUS_PENDING => '待审核',
        self::STATUS_WAIT    => '待支付',
        self::STATUS_SUCCESS => '成功',
        self::STATUS_FAIL    => '失败',
    ];

    public static array $typeMap = [
        self::TYPE_MANUAL   => '手动补单',
        self::TYPE_OFFLINE  => '线下充值',
        self::TYPE_CONTRACT => '合约充值',
        self::TYPE_THIRD    => '三方充值',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'pa_recharge_order',
            'comment' => '充值订单表',
            'columns' => [
                'id'             => ['type' => 'bigIncrements', 'comment' => '主键ID'],
                'order_no'       => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '订单号'],
                'user_id'        => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'is_virtual'     => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是否虚拟用户'],
                'money'          => ['type' => 'decimal', 'precision' => 20, 'scale' => 8, 'default' => 0, 'comment' => '充值金额'],
                'original_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 8, 'default' => 0, 'comment' => '原始金额'],
                'exchange_rate'  => ['type' => 'decimal', 'precision' => 20, 'scale' => 8, 'default' => 1, 'comment' => '汇率'],
                'type'           => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '类型:0手动 1线下 2合约 3三方'],
                'status'         => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '状态:0待审核 1待支付 2成功 3失败'],
                'pay_platform_id'         => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '支付通道ID'],
                'trade_no'       => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '第三方订单号'],
                'transaction_hash'           => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '交易哈希'],
                'chain_tx_id'           => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => 'TXID'],
                'voucher_url'            => ['type' => 'string', 'length' => 1000, 'default' => '', 'comment' => '付款凭证'],
                'remark'         => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '备注'],
                'user_note'      => ['type' => 'string', 'length' => 250, 'default' => '', 'comment' => '用户备注'],
                'paid_at'       => ['type' => 'unsignedBigInteger', 'nullable' => true, 'comment' => '支付时间(unix)'],
                'succeeded_at'   => ['type' => 'unsignedBigInteger', 'nullable' => true, 'comment' => '成功回调时间(unix)'],
                'admin_id'       => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '操作管理员ID'],
                'order_source'           => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '来源:1前端 2后台'],
                'ip'             => ['type' => 'string', 'length' => 45, 'default' => '', 'comment' => '操作IP'],
                'is_first'       => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是否首充'],
                'coin'           => ['type' => 'string', 'length' => 50, 'nullable' => true, 'comment' => '虚拟币类型'],
                'coin_amount'     => ['type' => 'decimal', 'precision' => 20, 'scale' => 8, 'nullable' => true, 'comment' => '虚拟币金额'],
                'result'         => ['type' => 'string', 'length' => 500, 'default' => '', 'comment' => '充值结果'],
                'to_address'     => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '收款地址'],
                'from_address'   => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '付款地址'],
                'pay_account'    => ['type' => 'string', 'length' => 250, 'default' => '', 'comment' => '付款账户'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['status', 'type']],
                ['columns' => ['order_no']],
                ['columns' => ['user_id']],
                ['columns' => ['pay_platform_id']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(PayPlatformModel::class, 'pay_platform_id');
    }
}
