<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCreditScoreModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_user_credit_score';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'total_score', 'trade_score', 'active_score', 'new_score', 'period',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_credit_score',
            'comment' => '用户信用评分表',
            'columns' => [
                'id'           => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'      => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'total_score'  => ['type' => 'integer', 'default' => 0, 'comment' => '总分'],
                'trade_score'  => ['type' => 'integer', 'default' => 0, 'comment' => '交易量分'],
                'active_score' => ['type' => 'integer', 'default' => 0, 'comment' => '活跃分'],
                'new_score'    => ['type' => 'integer', 'default' => 0, 'comment' => '新增分'],
                'period'       => ['type' => 'char', 'length' => 6, 'comment' => '评分月份YYYYMM'],
            ],
            'indexes' => [
                ['columns' => ['user_id']],
                ['columns' => ['period']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}

