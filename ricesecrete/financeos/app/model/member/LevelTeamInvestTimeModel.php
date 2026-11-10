<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class LevelTeamInvestTimeModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_level_team_invest_time';

    public const STATUS_DISABLE = 0;
    public const STATUS_ENABLE = 1;

    protected $fillable = [
        'user_id', 'level_id', 'started_at', 'ended_at',
        'invest_rate', 'profit_rate', 'status',
    ];

    public static function tableSchema(): array
    {
        return [
            'table' => 'me_level_team_invest_time',
            'comment' => '团队广告位理财时段',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '用户ID'],
                'level_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '等级ID'],
                'started_at' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '开始时间'],
                'ended_at' => ['type' => 'string', 'length' => 8, 'default' => '', 'comment' => '结束时间'],
                'invest_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '占余额比例'],
                'profit_rate' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '收益率'],
                'status' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '状态'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id', 'level_id', 'status'], 'name' => 'idx_level_team_invest_time'],
            ],
        ];
    }

    public static function getCurrentConfig(int $userId, int $levelId): ?self
    {
        $currentTime = date('H:i:s');

        return self::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->where('status', self::STATUS_ENABLE)
            ->where('started_at', '<=', $currentTime)
            ->where('ended_at', '>=', $currentTime)
            ->orderByDesc('id')
            ->first();
    }
}
