<?php

declare(strict_types=1);

namespace app\model\trade;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class TradeDividendDailyModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_trade_dividend_daily';

    protected $fillable = [
        'user_id', 'date', 'level', 'position_amount', 'direct_new', 'team_active',
        'studio_certified', 'reward', 'status', 'claimed_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'level' => 'integer',
        'studio_certified' => 'integer',
        'status' => 'integer',
        'claimed_at' => 'integer',
    ];

    public const STATUS_PENDING = 0;
    public const STATUS_CLAIMED = 1;

    public static function tableSchema(): array
    {
        return [
            'table' => 'me_trade_dividend_daily',
            'comment' => '用户每日交易分红',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'date' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '结算日期YYYYMMDD'],
                'level' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '分红等级V0-V5'],
                'position_amount' => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '仓位金额'],
                'direct_new' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '直推新增'],
                'team_active' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '团队活跃'],
                'studio_certified' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '是否认证工作室'],
                'reward' => ['type' => 'decimal', 'precision' => 20, 'scale' => 4, 'default' => 0, 'comment' => '分红奖励'],
                'status' => ['type' => 'tinyInteger', 'default' => 0, 'comment' => '0待领取 1已领取'],
                'claimed_at' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '领取时间'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'unique' => [['columns' => ['user_id', 'date']]],
            'indexes' => [['columns' => ['date']]],
        ];
    }
}
