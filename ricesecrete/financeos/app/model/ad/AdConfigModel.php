<?php

declare(strict_types=1);

namespace app\model\ad;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class AdConfigModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'ad_config';

    public const TYPE_EXPERIENCE = 1;
    public const TYPE_NORMAL = 2;
    public const TYPE_PROMOTION = 3;
    public const TYPE_ACTIVITY = 4;

    public static array $typeMap = [
        self::TYPE_EXPERIENCE => '体验区',
        self::TYPE_NORMAL => '未推广代理区',
        self::TYPE_PROMOTION => '已推广代理区',
        self::TYPE_ACTIVITY => '精选活动区',
    ];

    protected $fillable = [
        'level_id', 'level_name', 'price', 'price_text', 'invite_num', 'active_num', 'invite_num_amount',
        'income', 'income_max', 'income_text', 'active_income', 'invest_income_text',
        'invest_rate', 'excess_invest_rate', 'amount_range_inventory', 'active_rate', 'invest_max_rate',
        'invest_num', 'invest_profit', 'fee_rate', 'invest_day', 'invest_period_type',
        'invest_password', 'password_expired_at', 'task_num', 'limit_unlock_num', 'limit_unlock_num_text',
        'level_at', 'level_time_text', 'recharge_rebate', 'task_rebate', 'again_charge_percent',
        'again_charge_cap', 'withdraw_trx_min_amount', 'withdraw_usdt_min_amount', 'withdraw_max_usdt',
        'is_show', 'image_url', 'type', 'sort', 'is_can_buy', 'withdraw_week',
        'unlock_time_start', 'unlock_time_end', 'unlock_time_disabled_tips',
        'password_refresh_frequency', 'session_count',
    ];

    protected $casts = [
        'level_id' => 'integer',
        'invite_num' => 'integer',
        'active_num' => 'integer',
        'task_num' => 'integer',
        'limit_unlock_num' => 'integer',
        'level_at' => 'integer',
        'again_charge_percent' => 'integer',
        'again_charge_cap' => 'integer',
        'is_show' => 'integer',
        'type' => 'integer',
        'sort' => 'integer',
        'is_can_buy' => 'integer',
        'password_expired_at' => 'integer',
        'password_refresh_frequency' => 'integer',
        'session_count' => 'integer',
        'excess_invest_rate' => 'array',
        'amount_range_inventory' => 'array',
    ];

    public static function tableSchema(): array
    {
        $decimal = static fn (int $precision, int $scale, $default = 0) => [
            'type' => 'decimal', 'precision' => $precision, 'scale' => $scale, 'default' => $default,
        ];

        return [
            'table' => 'ad_config',
            'comment' => '广告位等级配置',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'level_id' => ['type' => 'unsignedInteger', 'comment' => '广告位等级ID'],
                'level_name' => ['type' => 'string', 'length' => 30, 'default' => '', 'comment' => '广告位名称'],
                'price' => array_merge($decimal(10, 2), ['comment' => '解锁金额']),
                'price_text' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '解锁金额文本'],
                'invite_num' => ['type' => 'integer', 'default' => 0, 'comment' => '解锁邀请人数'],
                'active_num' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '解锁昨日活跃人数'],
                'invite_num_amount' => array_merge($decimal(10, 2), ['comment' => '邀请人数有效充值']),
                'income' => array_merge($decimal(10, 2), ['comment' => '单个任务收益金额']),
                'income_max' => array_merge($decimal(10, 2), ['comment' => '单个任务最高收益金额']),
                'income_text' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '任务收益文本'],
                'active_income' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'unsigned' => true, 'default' => 0, 'comment' => '每个活跃用户增加金额'],
                'invest_income_text' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '理财收益文本'],
                'invest_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'unsigned' => true, 'default' => 0, 'comment' => '流量包占余额比例'],
                'excess_invest_rate' => ['type' => 'text', 'nullable' => true, 'comment' => '超额库存比例配置(JSON)'],
                'amount_range_inventory' => ['type' => 'text', 'nullable' => true, 'comment' => '不同金额区间库存设置(JSON)'],
                'active_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'unsigned' => true, 'default' => 0, 'comment' => '每个活跃用户增加比例'],
                'invest_max_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'unsigned' => true, 'default' => 0, 'comment' => '最高流量包比例'],
                'invest_num' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '流量包个数'],
                'invest_profit' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'unsigned' => true, 'default' => 0, 'comment' => '流量包收益率'],
                'fee_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'nullable' => true, 'default' => 0, 'comment' => '手续费比例'],
                'invest_day' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'unsigned' => true, 'default' => 0, 'comment' => '流量包周期'],
                'invest_period_type' => ['type' => 'string', 'length' => 10, 'default' => 'day', 'comment' => '周期类型 day/hour'],
                'invest_password' => ['type' => 'string', 'length' => 10, 'default' => '', 'comment' => '流量包密码'],
                'password_expired_at' => ['type' => 'integer', 'default' => 0, 'comment' => '密码到期时间'],
                'task_num' => ['type' => 'integer', 'default' => 0, 'comment' => '任务数'],
                'limit_unlock_num' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '限制解锁人数'],
                'limit_unlock_num_text' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '可用库存文本'],
                'level_at' => ['type' => 'integer', 'default' => 0, 'comment' => '有效天数'],
                'level_time_text' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '有效天数文本'],
                'recharge_rebate' => ['type' => 'string', 'length' => 255, 'default' => '[0,0,0]', 'comment' => '充值返利'],
                'task_rebate' => ['type' => 'string', 'length' => 255, 'default' => '[0,0,0]', 'comment' => '任务返利'],
                'again_charge_percent' => ['type' => 'integer', 'default' => 0, 'comment' => '复充送款百分比'],
                'again_charge_cap' => ['type' => 'integer', 'default' => 0, 'comment' => '复充送款封顶'],
                'withdraw_trx_min_amount' => array_merge($decimal(10, 2), ['comment' => '最小TRX提现金额']),
                'withdraw_usdt_min_amount' => array_merge($decimal(10, 2), ['comment' => '最小USDT提现金额']),
                'withdraw_max_usdt' => array_merge($decimal(10, 2), ['comment' => '最大提现金额(USDT)']),
                'is_show' => ['type' => 'tinyInteger', 'default' => 1, 'comment' => '是否显示'],
                'image_url' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '图片'],
                'type' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => 'VIP类型'],
                'sort' => ['type' => 'integer', 'default' => 0, 'comment' => '排序'],
                'is_can_buy' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '是否可解锁购买'],
                'withdraw_week' => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '可提现星期'],
                'unlock_time_start' => ['type' => 'string', 'length' => 8, 'default' => '00:00:00', 'comment' => '解锁开始时间'],
                'unlock_time_end' => ['type' => 'string', 'length' => 8, 'default' => '23:59:59', 'comment' => '解锁结束时间'],
                'unlock_time_disabled_tips' => ['type' => 'string', 'length' => 255, 'default' => '当前时间不允许解锁', 'comment' => '非交易时段提示'],
                'password_refresh_frequency' => ['type' => 'integer', 'default' => 0, 'comment' => '编码更新频率(分钟)'],
                'session_count' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '广告位购买场次'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['level_id'], 'unique' => true],
                ['columns' => ['is_show', 'sort']],
                ['columns' => ['type']],
            ],
        ];
    }
}
