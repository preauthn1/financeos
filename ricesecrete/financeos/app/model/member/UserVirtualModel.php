<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVirtualModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_user_virtual';

    protected $fillable = [
        'user_id', 'balance', 'total_income', 'today_income',
        'total_recharge', 'total_withdraw',
        'team_total', 'team_today_new', 'team_active', 'team_total_recharge',
        'level1_count', 'level1_active', 'level1_recharge', 'level1_brokerage',
        'level2_count', 'level2_active', 'level2_recharge', 'level2_brokerage',
        'level3_count', 'level3_active', 'level3_recharge', 'level3_brokerage',
        'today_invite_count', 'week_invite_count', 'month_invite_count',
        'credit_score',
    ];

    public static function tableSchema(): array
    {
        $d = fn (int $p, int $s) => ['type' => 'decimal', 'precision' => $p, 'scale' => $s, 'default' => 0, 'comment' => ''];

        return [
            'table'   => 'me_user_virtual',
            'comment' => '虚拟数据/团队统计',
            'columns' => [
                'id'      => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id' => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'balance'             => $d(15, 2) + ['comment' => '账户余额(展示用)'],
                'total_income'        => $d(15, 2) + ['comment' => '总收入'],
                'today_income'        => $d(15, 2) + ['comment' => '今日收益'],
                'total_recharge'      => $d(15, 2) + ['comment' => '累计充值'],
                'total_withdraw'      => $d(15, 2) + ['comment' => '累计提现'],
                'team_total'          => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '团队总人数'],
                'team_today_new'      => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '今日新增'],
                'team_active'         => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '团队活跃人数'],
                'team_total_recharge' => $d(15, 2) + ['comment' => '团队总充值'],
                'level1_count'        => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '一级团队人数'],
                'level1_active'       => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '一级活跃人数'],
                'level1_recharge'     => $d(15, 2) + ['comment' => '一级充值'],
                'level1_brokerage'    => $d(15, 2) + ['comment' => '一级佣金'],
                'level2_count'        => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '二级团队人数'],
                'level2_active'       => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '二级活跃人数'],
                'level2_recharge'     => $d(15, 2) + ['comment' => '二级充值'],
                'level2_brokerage'    => $d(15, 2) + ['comment' => '二级佣金'],
                'level3_count'        => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '三级团队人数'],
                'level3_active'       => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '三级活跃人数'],
                'level3_recharge'     => $d(15, 2) + ['comment' => '三级充值'],
                'level3_brokerage'    => $d(15, 2) + ['comment' => '三级佣金'],
                'today_invite_count'  => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '今日直推人数'],
                'week_invite_count'   => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '本周直推人数'],
                'month_invite_count'  => ['type' => 'unsignedBigInteger', 'default' => 0, 'comment' => '本月直推人数'],
                'credit_score'        => ['type' => 'integer', 'default' => 0, 'comment' => '信用分'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'unique'  => [['columns' => ['user_id']]],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
