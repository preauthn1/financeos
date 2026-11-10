<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelTeamModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_level_team';

    public const STATUS_DISABLE = 0;
    public const STATUS_ENABLE = 1;

    protected $fillable = [
        'user_id', 'level_id', 'income', 'income_max', 'active_income',
        'invest_rate', 'active_rate', 'invest_max_rate', 'invest_num',
        'invest_profit', 'invest_day', 'invest_period_type', 'invest_password',
        'type', 'status', 'subordinate', 'overdue_at',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'me_level_team',
            'comment' => '团队广告位单控/群控配置',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'level_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '等级ID'],
                'income' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '任务收益'],
                'income_max' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '最高任务收益'],
                'active_income' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '活跃加成金额'],
                'invest_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '占余额比例'],
                'active_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '活跃加成比例'],
                'invest_max_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '最高占余额比例'],
                'invest_num' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '广告位数量'],
                'invest_profit' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '收益率'],
                'invest_day' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '周期'],
                'invest_period_type' => ['type' => 'string', 'length' => 10, 'default' => 'day', 'comment' => '周期类型'],
                'invest_password' => ['type' => 'string', 'length' => 32, 'default' => '', 'comment' => '编码'],
                'type' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => 'VIP类型'],
                'status' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '状态'],
                'subordinate' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '对下级生效'],
                'overdue_at' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '配置过期时间'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id', 'level_id', 'status'], 'name' => 'idx_level_team_user'],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
