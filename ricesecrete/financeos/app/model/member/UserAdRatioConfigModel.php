<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAdRatioConfigModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_user_ad_ratio_config';

    protected $fillable = [
        'user_id', 'level_id', 'ratio', 'profit_rate', 'ad_cycle', 'apply_to_subordinates',
    ];

    protected $casts = [
        'ratio'       => 'array',
        'profit_rate' => 'array',
        'ad_cycle'    => 'array',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_ad_ratio_config',
            'comment' => '会员广告比例配置',
            'columns' => [
                'id'       => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'  => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'level_id' => ['type' => 'unsignedBigInteger', 'comment' => '等级ID'],
                'ratio'    => ['type' => 'json', 'comment' => '广告比例'],
                'profit_rate' => ['type' => 'json', 'nullable' => true, 'comment' => '收益率配置'],
                'ad_cycle'    => ['type' => 'json', 'nullable' => true, 'comment' => '广告周期配置'],
                'apply_to_subordinates' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是否应用到下级:0否 1是'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'unique'  => [['columns' => ['user_id', 'level_id']]],
            'indexes' => [
                ['columns' => ['user_id']],
                ['columns' => ['level_id']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
