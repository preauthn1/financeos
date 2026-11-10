<?php

declare(strict_types=1);

namespace app\model\ad;

use app\model\Concerns\DefinesTableSchema;
use app\model\member\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdProductOrderModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'ad_product_order';

    protected $fillable = [
        'user_id', 'level_id', 'ad_plan_days_config_id', 'order_no', 'cycle', 'invest_period_type',
        'ended_at', 'balance', 'price', 'invest_amount', 'finance_product_id', 'product_name',
        'profit', 'profit_amount', 'profit_type', 'settled_at', 'settlement_times',
        'started_at', 'bought_at', 'matched_at', 'fee_rate', 'fee', 'sort', 'status',
        'session_no', 'match_type', 'stock_range_min', 'stock_range_max', 'overdue_amount',
        'is_team_level', 'invest_rate', 'other_info',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'level_id' => 'integer',
        'status' => 'integer',
        'match_type' => 'integer',
        'profit_type' => 'integer',
    ];

    public const STATUS_PENDING = 0;
    public const STATUS_WAIT_BUY = 1;
    public const STATUS_BOUGHT = 2;
    /** @deprecated 兼容旧代码，与 STATUS_BOUGHT 相同 */
    public const STATUS_PURCHASED = self::STATUS_BOUGHT;
    public const STATUS_SETTLED = 3;
    public const STATUS_EXPIRED = 4;

    public static array $statusMap = [
        self::STATUS_PENDING   => "\u{5F85}\u{5339}\u{914D}",   // 待匹配
        self::STATUS_WAIT_BUY  => "\u{5F85}\u{8D2D}\u{4E70}",  // 待购买
        self::STATUS_BOUGHT    => "\u{5DF2}\u{8D2D}\u{4E70}",  // 已购买
        self::STATUS_SETTLED   => "\u{5DF2}\u{7ED3}\u{7B97}",  // 已结算
        self::STATUS_EXPIRED   => "\u{5DF2}\u{8FC7}\u{671F}",  // 已过期
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'ad_product_order',
            'comment' => '广告佝购买订坕表',
            'columns' => [
                'id'                  => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'             => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'level_id'            => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '等级ID'],
                'ad_plan_days_config_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '广告计划天数配置ID(ad_plan_days_config.id)'],
                'order_no'            => ['type' => 'string', 'length' => 64, 'default' => '', 'comment' => '订坕坷'],
                'cycle'               => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '投资天数/周期'],
                'invest_period_type'  => ['type' => 'string', 'length' => 10, 'default' => 'day', 'comment' => '周期类型:day/hour'],
                'ended_at'            => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '结束时间(unix)'],
                'balance'             => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '匹酝时余额'],
                'price'               => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '购买金额'],
                'invest_amount'               => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '投资金额'],
                'finance_product_id'  => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '产哝ID'],
                'product_name'=> ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '产哝坝称'],
                'profit'              => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '毝日收益率'],
                'profit_amount'        => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '收益金额'],
                'profit_type'         => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '收益类型'],
                'settled_at'     => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '结算时间(unix)'],
                'settlement_times'    => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '结算次数'],
                'started_at'          => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '开始时间(unix)'],
                'bought_at'            => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '购买时间(unix)'],
                'matched_at'          => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '匹酝时间(unix)'],
                'fee_rate'            => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '手续费比例'],
                'fee'                 => ['type' => 'decimal', 'precision' => 20, 'scale' => 2, 'default' => 0, 'comment' => '手续费'],
                'sort'                => ['type' => 'unsignedInteger', 'default' => 1, 'comment' => '排庝'],
                'status'              => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '状思'],
                'session_no'          => ['type' => 'string', 'length' => 64, 'default' => '', 'comment' => '时段编砝'],
                'match_type'          => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '匹酝类型:1正常 2超额'],
                'stock_range_min'     => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '库存金额区间min'],
                'stock_range_max'     => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '库存金额区间max'],
                'overdue_amount'       => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '信用预支阈值'],
                'is_team_level'       => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是坦坕控群控'],
                'invest_rate'         => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '坠余额比例'],
                'other_info'          => ['type' => 'text', 'nullable' => true, 'comment' => '弹窗附加信息JSON'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id', 'status']],
                ['columns' => ['status', 'created_at']],
                ['columns' => ['order_no']],
                ['columns' => ['session_no']],
                ['columns' => ['level_id', 'stock_range_min', 'stock_range_max', 'match_type', 'matched_at'], 'name' => 'idx_ad_order_stock_match'],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
